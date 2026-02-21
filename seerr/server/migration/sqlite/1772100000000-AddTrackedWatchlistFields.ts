import type { MigrationInterface, QueryRunner } from 'typeorm';

export class AddTrackedWatchlistFields1772100000000
  implements MigrationInterface
{
  name = 'AddTrackedWatchlistFields1772100000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(
      `ALTER TABLE "watchlist" ADD COLUMN "watched" boolean NOT NULL DEFAULT (0)`
    );
    await queryRunner.query(
      `ALTER TABLE "watchlist" ADD COLUMN "watchedAt" datetime`
    );
    await queryRunner.query(
      `ALTER TABLE "watchlist" ADD COLUMN "customCategory" varchar`
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(
      `ALTER TABLE "watchlist" DROP COLUMN "customCategory"`
    );
    await queryRunner.query(`ALTER TABLE "watchlist" DROP COLUMN "watchedAt"`);
    await queryRunner.query(`ALTER TABLE "watchlist" DROP COLUMN "watched"`);
  }
}
