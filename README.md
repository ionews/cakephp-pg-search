# Busca Textual com PostgreSQL no CakePHP

[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE) 
[![CI](https://github.com/ionews/cakephp-pg-search/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/ionews/cakephp-pg-search/actions/workflows/ci.yml)
[![Coverage](https://img.shields.io/codecov/c/github/ionews/cakephp-pg-search?style=flat-square)](https://codecov.io/gh/ionews/cakephp-pg-search/branch/main)
[![Downloads](https://img.shields.io/packagist/dt/ionews/cakephp-pg-search.svg?style=flat-square)](https://packagist.org/packages/ionews/cakephp-pg-search)
[![Latest Stable](https://img.shields.io/packagist/v/ionews/cakephp-pg-search.svg?style=flat-square&label=stable)](https://packagist.org/packages/ionews/cakephp-pg-search)

Adicione suporte a [Full Text Search do Postgres](https://www.postgresql.org/docs/current/textsearch.html) em sua aplicação CakePHP.

## Requisitos

 - PHP 7.2+
 - CakePHP 4.1+
 - PostgreSQL 9.6+

## Instalar

Inclua o plugin como dependência

```
composer require autopage/pg-search
```

## Uso

Para ter todos os recursos disponíveis, você deve configurar sua aplicação para usar o `Driver` fornecido aqui na conexão com o banco de dados. Ele habilitará o uso do `TableSchema` e `PostgresSchemaDialect` incluidos no plugin que estendem as versões padrão do CakePHP para implementar o tipo de coluna `tsvector` e aos índices do tipo `gin` e `gist`.

### Configuração

No seu `app_local.php`:

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

Outra configuração importante, mas opcional, é definir qual [_configuração de busca_](https://www.postgresql.org/docs/current/textsearch-configuration.html) o PostgreSQL deve usar na hora de indexar ou buscar um campo do tipo `tsvector`.

Você pode configurar ela com a chave `PgSearch.config_name`. Supondo que você criou no seu banco de dados uma configuração de nome **portuguese**, o seu `app_local.php` ficaria:

```php
    // ...
    'PgSearch' => [
        'config_name' => 'portuguese',
    ],
    // ...
```

### SearchableBehavior

Associe as tabelas que deseja tornar pesquisável ao behavior, desta forma, sempre que um registro for criado/editado/excluído, as informações serão propagadas para a tabela de busca.

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