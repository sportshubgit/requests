import type { MigrationInterface, QueryRunner } from 'typeorm';

export class AddDeclineReasonToMediaRequest1773200000000
  implements MigrationInterface
{
  name = 'AddDeclineReasonToMediaRequest1773200000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(
      `ALTER TABLE "media_request" ADD COLUMN "declineReason" text`
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(
      `ALTER TABLE "media_request" DROP COLUMN "declineReason"`
    );
  }
}
