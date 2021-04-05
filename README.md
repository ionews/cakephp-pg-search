# Busca Textual com PostgreSQL no CakePHP

[![pipeline status](https://git.autopage.inf.br/ionews/pg-search/badges/main/pipeline.svg)](https://git.autopage.inf.br/ionews/pg-search/-/commits/main) [![coverage report](https://git.autopage.inf.br/ionews/pg-search/badges/main/coverage.svg)](https://git.autopage.inf.br/ionews/pg-search/-/commits/main)

## Instalar

Inclua o plugin como dependência

```
composer require autopage/pg-search
```

## Uso

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
