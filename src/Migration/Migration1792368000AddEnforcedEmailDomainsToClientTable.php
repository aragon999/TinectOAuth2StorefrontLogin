<?php declare(strict_types=1);

namespace Tinect\OAuth2StorefrontLogin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1792368000AddEnforcedEmailDomainsToClientTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792368000;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->fetchFirstColumn(
            'SHOW COLUMNS FROM `tinect_oauth_storefront_client` LIKE \'enforced_email_domains\''
        );

        if (empty($columns)) {
            $connection->executeStatement('
                ALTER TABLE `tinect_oauth_storefront_client`
                ADD COLUMN `enforced_email_domains` JSON NULL;
            ');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
