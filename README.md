# Busca Textual com PostgreSQL no CakePHP

[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE) 
[![CI](https://github.com/ionews/cakephp-pg-search/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/ionews/cakephp-pg-search/actions/workflows/ci.yml)
[![Coverage](https://img.shields.io/codecov/c/github/ionews/cakephp-pg-search?style=flat-square)](https://codecov.io/gh/ionews/cakephp-pg-search/branch/main)
[![Downloads](https://img.shields.io/packagist/dt/autopage/pg-search.svg?style=flat-square)](https://packagist.org/packages/autopage/pg-search)
[![Latest Stable](https://img.shields.io/packagist/v/autopage/pg-search.svg?style=flat-square&label=stable)](https://packagist.org/packages/autopage/pg-search)

Adicione suporte a [Full Text Search do Postgres](https://www.postgresql.org/docs/current/textsearch.html) em sua aplicação CakePHP.

## Requisitos

 - PHP 7.2+
 - CakePHP 4.2.2+
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

#### Configurações disponíveis

 - **target**: `string`. Nome do repositório (`Table`) que deve ser usada para persistir os registros de forma pesquisável. Por padrão usa uma `Table` com nome igual a que está vinculada ao `Behavior`, adicionado o sufixo _'Searches'_. Exemplo: `Posts` -> `PostsSearches`.
 - **foreign_key**: `string`. Nome da coluna em **target** que referencia o registro original. Por padrão usa o nome da tabela no singular adicionado o sufixo _'\_id'_. Exemplo: `Posts` -> `post_id`
 - **mapper**: `callable`. Método/função que converte uma entidade do repositório original para uma de _target_. Por padrão, ele copia todos os campos da entidade original na entidade nova e associa a chave estrangeira da nova a chave primária da original.
 - **doIndex**: `bool|callable`. Se deve ou não indexar o registro. Permite um controle individual. Por padrão o valor é `true`.
 - **doDeindex**: `bool|callable`. Se deve ou não desindexar o registro. Permite um  controle individual e suporte a remoção lógica (_soft-delete_). Por padrão o valor é `false`.

#### Finder FTS

O _behavior_ disponibiliza o _finder_ de nome `fts`. Como todo _finder_, ele recebe uma _query_ e também retorna uma _query_. Dessa forma, você pode usar ele para preparar uma _query_ antes ou depois de chamar ele, estendendo as condições e qualquer outra operação possível em uma _query_.

Os parâmetros especiais desse _finder_ são:

 - **field**: `string` (_obrigatório_). Nome do campo do tipo tsvector onde a busca será feita
 - **value**: `string` (_obrigatório_). Valor que deve ser comparado com o campo
 - **highlight**: `boolean`. Flag indicando se deve ou não incluir destaque nos termos encontrados. Por padrão é desativado.
 - **highlight_field**: `string` (_obrigatório_ apenas se **highlight** for `true`). Nome do campo textual onde o highlight será aplicado
 - **exact**: `boolean`. Se a comparação será do tipo exata ou aproximada. Por padrão é aproximada (`false`).
 - **configuration**: `string`. Nome da configuração de busca usada na comparação. Por padrão, usa a mesma definida em `PgSearch.config_name`.
 - **ranked**: `boolean`. Flag indicando se a _query_ deve ser ordenada por _score_.

## FAQ

### Preciso mesmo usar o driver do plugin na minha aplicação?

Não, não precisa. Mas ao não usar, você terá de dizer em cada `Table` que possui coluna `tsvector` qual é essa coluna.

Nos seus testes unitários, também não será possível criar `Fixture` com esse tipo de coluna ou um dos índices `gin` e `gist`.

Em resumo: não precisa, mas deveria.

### Por que usar uma tabela separada para indexar?

É uma decisão pessoal, tirada após trabalhar com sistemas que migram de backend de busca/indexação ao longo do tempo. Separar as tabelas te fornece mais flexibilidade. E o _tradeoff_ é o uso um pouco maior de disco.

Essa tabela indexada pode ser uma versão desnormalizada de outra, permitindo que você faça várias consultas que exigiriam `joins` e `subqueries` de outra maneira. Em um segundo momento, quando passar a ter bilhões de registros, você pode querer desacoplar essa tabela do banco principal e passar para uma outra instância, ou mesmo para um serviço especializado como Elasticsearch, Solr e Sphinx.

### Entendi, mas posso usar a mesma tabela do registro principal?

Até pode, mas você ganharia apenas o `finder` como vantagem - os recursos de sincronização ficam desativados.