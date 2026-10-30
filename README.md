<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## NutriLabel — API

Backend em Laravel + PostgreSQL para base nutricional TACO, receitas e cálculo nutricional.

### Como rodar

```bash
composer install
cp .env.example .env          # Windows: Copy-Item .env.example .env
php artisan key:generate
docker compose up -d          # PostgreSQL (defina DB_PASSWORD no .env)
php artisan migrate
php artisan nutrition:import-taco
php artisan serve             # http://localhost:8000
```

### Convenções da API

- Base URL: `http://localhost:8000/api`
- Enviar sempre `Accept: application/json` (e `Content-Type: application/json` nos `POST`/`PUT`).
- Valores decimais (`yield_value`, `quantity`) são enviados e retornados como **string** (ex.: `"250.5"`), nunca como número de ponto flutuante.
- Erros de validação retornam `422` com o formato padrão do Laravel (`message` + `errors`). Recurso inexistente retorna `404`.
- Não há autenticação nesta versão.

### Endpoints

| Método | Endpoint | Descrição |
| :--- | :--- | :--- |
| `GET` | `/api/foods?q={termo}` | Busca alimentos da TACO por nome |
| `POST` | `/api/recipes` | Cria uma receita |
| `GET` | `/api/recipes/{recipe}` | Consulta uma receita |
| `PUT` | `/api/recipes/{recipe}` | Substitui integralmente uma receita |
| `POST` | `/api/recipes/{recipe}/nutrition-calculations` | Executa e persiste um cálculo nutricional |
| `GET` | `/api/nutrition-calculations/{calculation}` | Consulta um cálculo já realizado |

#### `GET /api/foods`

Busca parcial pelo nome, ignorando maiúsculas/minúsculas e acentos.

| Parâmetro | Tipo | Regras |
| :--- | :--- | :--- |
| `q` | string (query) | Obrigatório. Espaços externos são removidos; vazio → `422` |

```bash
curl -H "Accept: application/json" "http://localhost:8000/api/foods?q=arroz"
```

Resposta `200`:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Arroz, integral, cozido",
      "source": "TACO",
      "nutrients": [
        {
          "id": 1,
          "name": "Energia",
          "unit": "kcal",
          "amount_value": "124",
          "status": "EXACT"
        }
      ]
    }
  ]
}
```

`status` pode ser `EXACT`, `TRACE`, `NA`, `ABSENT` ou `REEVALUATION`. Quando não é `EXACT`, `amount_value` é `null` (nunca zero).

#### `POST /api/recipes` e `PUT /api/recipes/{recipe}`

O `PUT` usa o mesmo corpo do `POST` e substitui toda a receita e seus ingredientes (sem `PATCH`/`DELETE`).

| Campo | Tipo | Regras |
| :--- | :--- | :--- |
| `name` | string | Obrigatório |
| `yield_value` | string | Obrigatório; decimal maior que zero (ex.: `"500"`, `"250.5"`) |
| `yield_unit` | string | Obrigatório; `g` ou `ml` |
| `ingredients` | array | Obrigatório; mínimo 1 item |
| `ingredients.*.food_id` | integer | Obrigatório; deve existir em `foods` |
| `ingredients.*.quantity` | string | Obrigatório; decimal maior que zero |
| `ingredients.*.unit` | string | Obrigatório; somente `g` |
| `ingredients.*.position` | integer | Obrigatório; mínimo 1 e único na receita |

```bash
curl -X POST http://localhost:8000/api/recipes \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{
    "name": "Geleia de amora",
    "yield_value": "500",
    "yield_unit": "g",
    "ingredients": [
      { "food_id": 1, "quantity": "300", "unit": "g", "position": 1 },
      { "food_id": 2, "quantity": "150.5", "unit": "g", "position": 2 }
    ]
  }'
```

Resposta (`POST`, `PUT` e `GET /api/recipes/{recipe}`):

```json
{
  "data": {
    "id": 1,
    "name": "Geleia de amora",
    "yield_value": "500",
    "yield_unit": "g",
    "ingredients": [
      { "food_id": 1, "quantity": "300", "unit": "g", "position": 1 },
      { "food_id": 2, "quantity": "150.5", "unit": "g", "position": 2 }
    ]
  }
}
```

#### `POST /api/recipes/{recipe}/nutrition-calculations`

Sem corpo. Calcula a composição nutricional da receita (contribuição por ingrediente, consolidação e normalização pelo rendimento) e grava um snapshot **imutável**. Cada chamada cria uma nova execução.

```bash
curl -X POST -H "Accept: application/json" \
  http://localhost:8000/api/recipes/1/nutrition-calculations
```

#### `GET /api/nutrition-calculations/{calculation}`

Retorna o snapshot de uma execução, idêntico ao retornado na criação.

```bash
curl -H "Accept: application/json" http://localhost:8000/api/nutrition-calculations/1
```

Estrutura da resposta:

```json
{
  "data": {
    "id": 1,
    "recipe_id": 1,
    "rules_version": "...",
    "yield": { "value": "500", "unit": "g" },
    "ingredients": [
      {
        "recipe_ingredient_id": 1,
        "position": 1,
        "quantity": "300",
        "unit": "g",
        "food": { "id": 1, "source": "TACO", "source_code": "..." }
      }
    ],
    "nutrients": [
      {
        "nutrient": { "id": 1, "name": "Energia", "unit": "kcal" },
        "contributions": [
          {
            "recipe_ingredient_id": 1,
            "food_nutrient_id": 10,
            "status": "EXACT",
            "source_amount_per_100g": "124",
            "missing_relation": false,
            "value": { "numerator": "372", "denominator": "1" }
          }
        ],
        "total": {
          "value": { "numerator": "372", "denominator": "1" },
          "complete": true,
          "qualitative_states": [],
          "has_missing_relation": false
        },
        "reference": {
          "basis": "100",
          "unit": "g",
          "value": { "numerator": "372", "denominator": "5" },
          "complete": true,
          "qualitative_states": [],
          "has_missing_relation": false
        }
      }
    ]
  }
}
```

Como interpretar:

- Valores numéricos são **frações exatas** (`numerator` / `denominator`, ambos strings), sem arredondamento.
- `contribution = quantity × source_amount_per_100g ÷ 100` (somente para status `EXACT`).
- `reference.value = total × 100 ÷ yield_value`, na base `100 g` ou `100 ml` conforme `yield.unit`.
- `value` é `null` quando não há valor numérico (`TRACE`, `NA`, `ABSENT`, `REEVALUATION` ou relação ausente); esses casos nunca são convertidos em zero.
- `complete: false` indica que o total/referência soma apenas as contribuições `EXACT` e há informação não numérica; `qualitative_states` lista os estados encontrados e `has_missing_relation` indica nutriente sem relação com o alimento.

Os exemplos acima são ilustrativos; os valores reais dependem dos dados importados da TACO.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
