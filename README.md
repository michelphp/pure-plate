# PurePlate

**A lightweight template engine for PHP. The syntax of Jinja. The power of native PHP. None of the weight.**

PurePlate parses templates with PHP's native `token_get_all()` lexer and compiles them to plain, cached PHP. No runtime overhead. No bloat. No magic.

```jinja
{% extends "layout.tpl" %}

{% block content %}
    <h1>Hello, {{ user.name|upper }}!</h1>

    {% if items is not empty %}
        <ul>
        {% foreach items as item %}
            <li>{{ item.title }} — {{ item.price|number_format(2, ',', ' ') }} €</li>
        {% endforeach %}
        </ul>
    {% endif %}
{% endblock %}
```

---

## Why PurePlate

### 1. Any PHP function works as a filter — out of the box

In Twig, every filter must be registered:

```php
// Twig: you have to declare each function as a filter
$twig->addFilter(new TwigFilter('upper', 'strtoupper'));
$twig->addFilter(new TwigFilter('format', 'number_format'));
```

In PurePlate, every PHP function is already a filter:

```jinja
{{ name|strtoupper }}
{{ price|number_format(2, ',', ' ') }}
{{ text|substr(0, 100) }}
{{ items|count }}
{{ date|date("Y-m-d") }}
```

No registration. No wrappers. The entire PHP standard library is available immediately.

### 2. Lexer-based, not regex-based

Templates are tokenized with PHP's own `token_get_all()` — the same lexer PHP uses to parse its own source code. This means:

- Correct handling of strings, escapes, nested quotes
- No regex edge-cases that break on unusual input
- Predictable, deterministic parsing

### 3. Compile-time validation

Generated PHP is validated with `TOKEN_PARSE` **before** being cached. If the compilation produces invalid PHP, you know immediately — not at runtime, not in production.

### 4. Source-mapped errors

Every compiled line carries a comment pointing back to the original template:

```php
<?php /*L:14;F:templates/page.tpl*/ echo htmlspecialchars(...); ?>
```

When an error happens, PurePlate rewrites the exception to point at the **template file and line**, not the cached PHP file.

```
PurePlate Error: Undefined variable $username [At: templates/page.tpl:14]
```

### 5. Auto-escaping by default

`{{ var }}` is always passed through `htmlspecialchars(..., ENT_QUOTES)`. Output is safe by default.

---

## Comparison

|                              | Twig 3      | PurePlate   |
|------------------------------|-------------|-------------|
| Syntax                       | Twig        | Twig-like   |
| PHP function as filter       | ❌ Must register | ✅ Native |
| Compile-time syntax check    | ❌          | ✅          |
| Source-mapped errors         | ⚠️ Complex   | ✅ Inline   |
| PHP 7.4 support              | ⚠️ Twig 3.x only | ✅      |
| Dependencies                 | Several     | Zero        |
| Auto-escape                  | ✅          | ✅          |

---

## Installation

```bash
composer require michel/pure-plate
```

PHP 7.4 or higher. No other runtime dependencies.

---

## Quick Start

```php
use Michel\PurePlate\Engine;

$plate = new Engine(__DIR__ . '/templates');

echo $plate->render('page.tpl', [
    'user'  => ['name' => 'fady'],
    'items' => [
        ['title' => 'Item A', 'price' => 19.90],
        ['title' => 'Item B', 'price' => 42.00],
    ],
]);
```

---

## Syntax

### Output

```jinja
{{ variable }}                       {# auto-escaped #}
{{ user.name }}                      {# same as user->name #}
{{ user.getName() }}                 {# method call #}
{{ value|filter }}                   {# any PHP function #}
{{ value|filter(arg1, arg2) }}       {# with arguments #}
```

### Control structures

```jinja
{% if condition %} ... {% elseif other %} ... {% else %} ... {% endif %}
{% foreach items as item %} ... {% endforeach %}
{% for i = 0; i < 10; i++ %} ... {% endfor %}
{% while condition %} ... {% endwhile %}
```

### Tests

```jinja
{% if list is empty %} ... {% endif %}
{% if list is not empty %} ... {% endif %}
{% if not active %} ... {% endif %}
```

### Variable assignment

```jinja
{% set total = price * quantity %}
```

### Template inheritance

```jinja
{# layout.tpl #}
<html>
<body>
    {% block content %}{% endblock %}
</body>
</html>

{# page.tpl #}
{% extends "layout.tpl" %}
{% block content %}
    <h1>Hello</h1>
{% endblock %}
```

### Includes

```jinja
{% include "partials/header.tpl" %}
```

### Comments

```jinja
{# This will not appear in the output #}
```

---

## Dev mode

In dev mode, templates are recompiled on every request:

```php
$plate = new Engine(__DIR__ . '/templates', devMode: true);
```

In production (default), templates are compiled once and cached. The cache is invalidated automatically when the template source changes.

---

## Cache directory

By default, compiled templates are stored in the system temp directory. To customize:

```php
$plate = new Engine(
    templateDir: __DIR__ . '/templates',
    devMode: false,
    cacheDir: __DIR__ . '/var/cache/plate'
);
```

---

## Globals

Variables passed as globals are available in every template without being re-passed at each render:

```php
$plate = new Engine(__DIR__ . '/templates', globals: [
    'siteTitle' => 'My Site',
    'version'   => '1.0',
]);
```

---

## License

Mozilla Public License 2.0
