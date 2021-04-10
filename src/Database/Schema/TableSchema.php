<?php
declare(strict_types=1);

namespace Autopage\PgSearch\Database\Schema;

use Cake\Database\Schema\TableSchema as BaseSchema;

/**
 * Allow new index types
 */
class TableSchema extends BaseSchema
{
    /**
     * Tsvector column type
     *
     * @var string
     */
    public const TYPE_TSVECTOR = 'tsvector';

    /**
     * Gin - index type
     *
     * @var string
     */
    public const INDEX_GIN = 'gin';

    /**
     * Gist - index type
     *
     * @var string
     */
    public const INDEX_GIST = 'gist';

    /**
     * Names of the valid index types.
     *
     * @var array
     */
    protected static $_validIndexTypes = [
        self::INDEX_INDEX,
        self::INDEX_FULLTEXT,
        self::INDEX_GIN,
        self::INDEX_GIST,
    ];
}
