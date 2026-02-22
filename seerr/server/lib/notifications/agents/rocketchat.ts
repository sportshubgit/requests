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

class RocketChatAgent
  extends BaseAgent<NotificationAgentRocketChat>
  implements NotificationAgent
{
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
      message += `\n\nComment from ${payload.comment.user.displayName}: ${payload.comment.message}`;
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

    try {
      await axios.post<RocketChatPayload>(
        `${serverUrl}/api/v1/chat.postMessage`,
        {
          channel,
          text,
        },
        {
          headers: {
            'X-Auth-Token': settings.options.authToken,
            'X-User-Id': settings.options.userId,
          },
        }
      );

      return true;
    } catch (e) {
      logger.error('Error sending Rocket.Chat notification', {
        label: 'Notifications',
        channel,
        errorMessage: e.message,
        response: e?.response?.data,
      });

      return false;
    }
  }

  private getUsername(user?: User): string | null {
    if (!user) {
      return null;
    }

    const username =
      user.username || user.plexUsername || user.jellyfinUsername || '';

    return username.trim() ? username.trim() : null;
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

    if (
      payload.notifyUser &&
      payload.notifyUser.settings?.hasNotificationType(
        NotificationAgentKey.ROCKETCHAT,
        type
      )
    ) {
      const username = this.getUsername(payload.notifyUser);
      if (username) {
        logger.debug('Sending Rocket.Chat notification', {
          label: 'Notifications',
          recipient: payload.notifyUser.displayName,
          type: Notification[type],
          subject: payload.subject,
        });

        const sent = await this.postMessage(
          this.normalizeChannel(username),
          message
        );
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
          u.settings?.hasNotificationType(NotificationAgentKey.ROCKETCHAT, type) &&
          shouldSendAdminNotification(type, u, payload)
      )) {
        const username = this.getUsername(user);
        if (!username) {
          continue;
        }

        logger.debug('Sending Rocket.Chat notification', {
          label: 'Notifications',
          recipient: user.displayName,
          type: Notification[type],
          subject: payload.subject,
        });

        const sent = await this.postMessage(this.normalizeChannel(username), message);
        if (!sent) {
          return false;
        }
      }
    }

    return true;
  }
}

export default RocketChatAgent;
