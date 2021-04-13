# Busca Textual com PostgreSQL no CakePHP

[![pipeline status](https://git.autopage.inf.br/ionews/pg-search/badges/main/pipeline.svg)](https://git.autopage.inf.br/ionews/pg-search/-/commits/main) [![coverage report](https://git.autopage.inf.br/ionews/pg-search/badges/main/coverage.svg)](https://git.autopage.inf.br/ionews/pg-search/-/commits/main)

## Instalar

Inclua o plugin como dependência

```
composer require autopage/pg-search
```

## Uso

Para ter todos os recursos disponíveis, você deve configurar sua aplicação para usar o `Driver` fornecido aqui na conexão com o banco de dados. Ele habilitará o uso do `TableSchema` e `PostgresSchemaDialect` incluidos no plugin que estendem as versões padrão do CakePHP para implementar o tipo de coluna `tsvector` e aos índices do tipo `gin` e `gist`.

### Configuração

No seu `app_local.php`

```php
    // ...
    'Datasources' => [
        'default' => [
            'className' => \Cake\Database\Connection::class,
            'driver' => \Autopage\PgSearch\Database\Driver\Postgres::class,
            // O restante da sua configuração vem normalmente
            // ...
        ],
    // ...
```

### SearchableBehavior

Associe as tabelas que deseja tornar pesquisável ao behavior, desta forma, sempre que um registro for criado/editado/excluído, as informações são propagadas para a tabela de busca.

```php
    /**
     * Método de inicialização da Table
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->addBehavior('Searchable', [
            'foreign_key' => 'origem_id',
            'mapper' => function ($entidade) {
                return [
                    'origem_id' => $entidade->id,
                    'conteudo' => $entidade->descricao,
                ];
            },
        ]);
    }
```

Como `mapper` você pode informar qualquer `callable`, a única restrição é que ele deve retornar uma entidade do mesmo tipo que o repositório (tabela) que vai salvar os registros indexados utiliza.