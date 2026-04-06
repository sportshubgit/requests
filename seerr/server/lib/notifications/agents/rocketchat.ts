import { IssueStatus, IssueTypeName } from '@server/constants/issue';
import { MediaStatus } from '@server/constants/media';
import { getRepository } from '@server/datasource';
import { User } from '@server/entity/User';
import type { NotificationAgentRocketChat } from '@server/lib/settings';
import { NotificationAgentKey, getSettings } from '@server/lib/settings';
import logger from '@server/logger';
import axios from 'axios';
import {
  Notification,
  hasNotificationType,
  shouldSendAdminNotification,
} from '..';
import type { NotificationAgent, NotificationPayload } from './agent';
import { BaseAgent } from './agent';

interface RocketChatPayload {
  channel: string;
  text: string;
}

interface RocketChatRoomPayload {
  roomId: string;
  text: string;
}

class RocketChatAgent
  extends BaseAgent<NotificationAgentRocketChat>
  implements NotificationAgent
{
  private readonly automatedFooter =
    "This is an automated message and replies will not be seen. If you have a query, please message in 'Plex Requests & Plex Issues'.";

  protected getSettings(): NotificationAgentRocketChat {
    if (this.settings) {
      return this.settings;
    }

    const settings = getSettings();

    return settings.notifications.agents.rocketchat;
  }

  public shouldSend(): boolean {
    const settings = this.getSettings();

    return !!(
      settings.enabled &&
      settings.options.serverUrl &&
      settings.options.userId &&
      settings.options.authToken
    );
  }

  private buildMessage(type: Notification, payload: NotificationPayload): string {
    const settings = getSettings();
    const { applicationUrl, applicationTitle } = settings.main;

    let message = payload.event
      ? `*${payload.event}* - *${payload.subject}*`
      : `*${payload.subject}*`;

    if (payload.message) {
      message += `\n${payload.message}`;
    }

    if (payload.request) {
      message += `\n\nRequested By: ${payload.request.requestedBy.displayName}`;

      let status = '';
      switch (type) {
        case Notification.MEDIA_AUTO_REQUESTED:
          status =
            payload.media?.status === MediaStatus.PENDING
              ? 'Pending Approval'
              : 'Processing';
          break;
        case Notification.MEDIA_PENDING:
          status = 'Pending Approval';
          break;
        case Notification.MEDIA_APPROVED:
        case Notification.MEDIA_AUTO_APPROVED:
          status = 'Processing';
          break;
        case Notification.MEDIA_AVAILABLE:
          status = 'Available';
          break;
        case Notification.MEDIA_DECLINED:
          status = 'Declined';
          break;
        case Notification.MEDIA_FAILED:
          status = 'Failed';
          break;
      }

      if (status) {
        message += `\nRequest Status: ${status}`;
      }
    } else if (payload.comment) {
      message += `\n\nComment: ${payload.comment.message}`;
    } else if (payload.issue) {
      message += `\n\nReported By: ${payload.issue.createdBy.displayName}`;
      message += `\nIssue Type: ${IssueTypeName[payload.issue.issueType]}`;
      message += `\nIssue Status: ${
        payload.issue.status === IssueStatus.OPEN ? 'Open' : 'Resolved'
      }`;
    }

    for (const extra of payload.extra ?? []) {
      message += `\n${extra.name}: ${extra.value}`;
    }

    if (type === Notification.MEDIA_AVAILABLE) {
      message +=
        '\n\nThis content will be available in your apps after the next content scan.';
    }

    const url = applicationUrl
      ? payload.issue
        ? `${applicationUrl}/issues/${payload.issue.id}`
        : payload.media
          ? `${applicationUrl}/${payload.media.mediaType}/${payload.media.tmdbId}`
          : undefined
      : undefined;

    if (url) {
      message += `\n\nView ${
        payload.issue ? 'Issue' : 'Media'
      } in ${applicationTitle}: ${url}`;
    }

    message += `\n\n${this.automatedFooter}`;

    return message;
  }

  private normalizeChannel(channel: string): string {
    const trimmed = channel.trim();

    if (!trimmed) {
      return '';
    }

    return trimmed.startsWith('@') || trimmed.startsWith('#')
      ? trimmed
      : `@${trimmed}`;
  }

  private async postMessage(channel: string, text: string): Promise<boolean> {
    const settings = this.getSettings();
    const serverUrl = settings.options.serverUrl.replace(/\/+$/, '');
    const headers = {
      'X-Auth-Token': settings.options.authToken,
      'X-User-Id': settings.options.userId,
    };

    try {
      await axios.post<RocketChatPayload>(
        `${serverUrl}/api/v1/chat.postMessage`,
        {
          channel,
          text,
        },
        {
          headers,
        }
      );

      return true;
    } catch (e) {
      const errorType = e?.response?.data?.errorType;
      if (channel.startsWith('@') && errorType === 'invalid-channel') {
        const username = channel.slice(1);
        const roomId = await this.ensureDirectRoom(serverUrl, headers, username);

        if (roomId) {
          try {
            await axios.post<RocketChatRoomPayload>(
              `${serverUrl}/api/v1/chat.postMessage`,
              {
                roomId,
                text,
              },
              { headers }
            );

            logger.debug(
              'Recovered Rocket.Chat delivery via direct room fallback',
              {
                label: 'Notifications',
                username,
                roomId,
              }
            );

            return true;
          } catch (roomError) {
            logger.error(
              'Rocket.Chat direct room fallback failed',
              {
                label: 'Notifications',
                username,
                roomId,
                errorMessage: roomError.message,
                response: roomError?.response?.data,
              }
            );
          }
        }
      }

      logger.error('Error sending Rocket.Chat notification', {
        label: 'Notifications',
        channel,
        errorMessage: e.message,
        response: e?.response?.data,
      });

      return false;
    }
  }

  private async ensureDirectRoom(
    serverUrl: string,
    headers: { 'X-Auth-Token': string; 'X-User-Id': string },
    username: string
  ): Promise<string | null> {
    const normalizedUsername = username.replace(/^@+/, '').trim();
    if (!normalizedUsername) {
      return null;
    }

    try {
      const byUsername = await axios.post(
        `${serverUrl}/api/v1/im.create`,
        { username: normalizedUsername },
        { headers }
      );

      const roomId =
        byUsername?.data?.room?._id ??
        byUsername?.data?.room?.rid ??
        byUsername?.data?._id ??
        byUsername?.data?.rid ??
        null;

      if (roomId) {
        return roomId;
      }
    } catch (_e) {
      // Try alternate payload shape below.
    }

    try {
      const byUsernames = await axios.post(
        `${serverUrl}/api/v1/im.create`,
        { usernames: [normalizedUsername] },
        { headers }
      );

      return (
        byUsernames?.data?.room?._id ??
        byUsernames?.data?.room?.rid ??
        byUsernames?.data?._id ??
        byUsernames?.data?.rid ??
        null
      );
    } catch (e) {
      logger.debug('Rocket.Chat im.create failed', {
        label: 'Notifications',
        username: normalizedUsername,
        errorMessage: e.message,
        response: e?.response?.data,
      });

      return null;
    }
  }

  private getUsernames(user?: User): string[] {
    if (!user) {
      return [];
    }

    const candidates = [
      user.username,
      user.plexUsername,
      user.jellyfinUsername,
      user.displayName,
      user.email?.split('@')[0],
    ]
      .map((candidate) => candidate?.trim() ?? '')
      .filter((candidate) => !!candidate);
    const expanded = candidates.flatMap((candidate) => {
      const lower = candidate.toLowerCase();
      const compact = lower.replace(/\s+/g, '');

      return compact && compact !== candidate
        ? [candidate, lower, compact]
        : lower !== candidate
          ? [candidate, lower]
          : [candidate];
    });

    return [...new Set(expanded)];
  }

  private isIssueNotification(type: Notification): boolean {
    return (
      type === Notification.ISSUE_CREATED ||
      type === Notification.ISSUE_COMMENT ||
      type === Notification.ISSUE_RESOLVED ||
      type === Notification.ISSUE_REOPENED
    );
  }

  private shouldSendToUser(type: Notification, payload: NotificationPayload): boolean {
    if (!payload.notifyUser) {
      return false;
    }

    if (!payload.notifyUser.settings) {
      return true;
    }

    // Keep issue reporting reliable even when older user notification bitmasks
    // are stale or missing issue flags.
    if (this.isIssueNotification(type)) {
      return true;
    }

    return payload.notifyUser.settings.hasNotificationType(
      NotificationAgentKey.ROCKETCHAT,
      type
    );
  }

  private shouldSendToAdmin(type: Notification, user: User): boolean {
    if (!user.settings) {
      return this.isIssueNotification(type);
    }

    if (this.isIssueNotification(type)) {
      return true;
    }

    return user.settings.hasNotificationType(NotificationAgentKey.ROCKETCHAT, type);
  }

  public async send(
    type: Notification,
    payload: NotificationPayload
  ): Promise<boolean> {
    const settings = this.getSettings();
    const message = this.buildMessage(type, payload);

    if (
      payload.notifySystem &&
      hasNotificationType(type, settings.types ?? 0) &&
      settings.options.systemChannel
    ) {
      const systemChannel = this.normalizeChannel(settings.options.systemChannel);
      if (systemChannel) {
        logger.debug('Sending Rocket.Chat notification', {
          label: 'Notifications',
          type: Notification[type],
          subject: payload.subject,
          channel: systemChannel,
        });

        const sent = await this.postMessage(systemChannel, message);
        if (!sent) {
          return false;
        }
      }
    }

    if (this.shouldSendToUser(type, payload)) {
      const notifyUser = payload.notifyUser;
      if (!notifyUser) {
        return true;
      }

      const usernames = this.getUsernames(notifyUser);
      if (usernames.length > 0) {
        logger.debug('Sending Rocket.Chat notification', {
          label: 'Notifications',
          recipient: notifyUser.displayName,
          type: Notification[type],
          subject: payload.subject,
          usernames,
        });

        let sent = false;
        for (const username of usernames) {
          sent = await this.postMessage(this.normalizeChannel(username), message);
          if (sent) {
            break;
          }
        }

        if (!sent) {
          return false;
        }
      }
    }

    if (payload.notifyAdmin) {
      const userRepository = getRepository(User);
      const users = await userRepository.find();

      for (const user of users.filter(
        (u) =>
          this.shouldSendToAdmin(type, u) &&
          shouldSendAdminNotification(type, u, payload)
      )) {
        const usernames = this.getUsernames(user);
        if (usernames.length === 0) {
          continue;
        }

        logger.debug('Sending Rocket.Chat notification', {
          label: 'Notifications',
          recipient: user.displayName,
          type: Notification[type],
          subject: payload.subject,
          usernames,
        });

        let sent = false;
        for (const username of usernames) {
          sent = await this.postMessage(this.normalizeChannel(username), message);
          if (sent) {
            break;
          }
        }

        if (!sent) {
          return false;
        }
      }
    }

    return true;
  }
}

export default RocketChatAgent;
