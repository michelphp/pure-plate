# PurePlate

**The template engine that compiles to the PHP you would have written by hand.**

**If you know PHP, you already know PurePlate: same rules, lighter syntax.** Every PHP function is a filter. Errors point at your template line. **~2.4× faster than Twig 3**, in two small classes with zero dependencies.

```jinja
{% extends "layout.tpl" %}

{% block content %}
    <h1>Hello, {{ user.name|strtoupper }}!</h1>

    {% if items is not empty %}
        <ul>
        {% foreach items as item %}
            <li>{{ item.title }} — {{ item.price|number_format(2, ',', ' ') }} €</li>
        {% endforeach %}
        </ul>
    {% endif %}
{% endblock %}
```

Becomes, once, in the cache:

```php
<h1>Hello, <?php echo htmlspecialchars((string)(strtoupper($user->name)), ENT_QUOTES); ?>!</h1>
```

No runtime interpretation, no attribute resolver, no extension layer. Just PHP.

---

## Why PurePlate

**Nothing new to learn.** PurePlate is not a new language: it is PHP with a lighter syntax. Expressions, operators, functions, objects and arrays behave exactly as in PHP, because they *are* PHP. No expression language to learn, no surprises, no documentation to dig through.

**Fast.** Templates compile to plain PHP: `user.name` is `$user->name`, `name|strtoupper` is `strtoupper($name)`. Nothing is resolved at runtime, so there is nothing to slow you down. See the [benchmark](#benchmark).

**Every PHP function is a filter.** No registration, no wrappers. `strtoupper`, `number_format`, `substr`, `count`, `json_encode`, your own functions: all available, right now.

**Errors point at your template.** A typo in a layout, a missing variable in an include, an exception thrown by your own code: the exception's `getFile()` and `getLine()` are the template file and line. Your IDE, logs and error pages take you straight there.

**Safe by default.** `{{ … }}` is always escaped with `htmlspecialchars(…, ENT_QUOTES)`.

**Small enough to read.** Two classes, about 500 lines, no dependencies. You can understand all of it in an afternoon.

---

## Benchmark

The same page rendered by both engines: a layout with two blocks, a loop over 100 objects, a ternary, two filters, and HTML escaping. Both run in production mode with a warm cache. The script checks that both produce the same HTML before measuring.

| | PurePlate | Twig 3.30 | |
|---|---:|---:|---|
| Render | 82 µs | 194 µs | **2.4× faster** |
| New engine + render (≈ one request) | 83 µs | 221 µs | **2.7× faster** |

<sub>PHP 8.2.29, OPcache on, Xdebug off, Debian VM (Vagrant), local disk. Best of 5 rounds of 2,000 renders. Numbers depend on your machine and templates: run it yourself.</sub>

```bash
composer install
php -d xdebug.mode=off -d opcache.enable_cli=1 benchmark/run.php   # first run compiles the templates
php -d xdebug.mode=off -d opcache.enable_cli=1 benchmark/run.php   # measures
```

---

## Installation

```bash
composer require michel/pure-plate
```

PHP 7.4 or higher, with the standard `tokenizer` extension. No other dependency.

---

## Quick start

```php
use Michel\PurePlate\Engine;

$plate = new Engine(__DIR__ . '/templates');

echo $plate->render('page.tpl', [
    'user'  => (object) ['name' => 'fady'],
    'items' => [
        (object) ['title' => 'Item A', 'price' => 19.90],
        (object) ['title' => 'Item B', 'price' => 42.00],
    ],
]);
```

```php
new Engine(
    string  $templateDir,
    bool    $devMode = false,   // true: recompile templates when they change
    ?string $cacheDir = null,   // default: system temp dir
    array   $globals = []       // variables available in every template
);
```

---

## Syntax

A template expression is a PHP expression, with three shortcuts:

| You write | PHP runs | |
|---|---|---|
| `title` | `$title` | a bare word is a variable |
| `user.name` | `$user->name` | the dot is the object operator |
| `price\|round(2)` | `round($price, 2)` | the pipe passes the value to a function |

Plus readable words for conditions: `and`, `or`, `not`, `is empty`, `is not empty`. Everything else (strings, operators, `[]`, `? :`, `??`, function and method calls) is written as in PHP.

The one PHP operator you trade away is `.` for concatenation, since the dot now reads objects. Just print side by side, or use a function: `{{ first }} {{ last }}`, `{{ sprintf('%s %s', first, last) }}`.

### Output

```jinja
{{ title }}                     {# escaped #}
{{ user.name }}                 {# object property: $user->name #}
{{ user.getName() }}            {# method call #}
{{ config['env'] }}             {# array key #}
{{ tags[0] }}                   {# array index #}
{{ isAdmin ? 'admin' : 'guest' }}
{{ nickname ?? 'anonymous' }}
{{ Order::STATUS_PAID }}        {# class constant #}
{{ Money::format(price) }}      {# static method #}
```

**Objects and arrays stay distinct**, as in PHP: the dot is for objects (`user.name` → `$user->name`), brackets are for arrays (`user['name']`). Chain them freely: `user.profile.address.city`, `tags[0].name`, `config['db'].host`, `user.getProfile().name`. What you read is what runs.

### Filters

Any PHP function, and any function you declare, is a filter. The value becomes the **first** argument:

```jinja
{{ name|strtoupper }}                    {# strtoupper($name) #}
{{ price|number_format(2, ',', ' ') }}   {# number_format($price, 2, ',', ' ') #}
{{ text|substr(0, 100) }}                {# substr($text, 0, 100) #}
{{ items|count }}                        {# count($items) #}
```

Functions are also callable directly, which is what you need when the value is not the first argument:

```jinja
{{ date('Y-m-d', timestamp) }}
{{ implode(', ', tags) }}
```

A filter applies to **the value just before it**, wherever it is. Use parentheses to apply it to more:

```jinja
{{ isAdmin ? name|strtoupper : 'guest' }}    {# $isAdmin ? strtoupper($name) : 'guest' #}
{{ sprintf('%s!', name|ucfirst) }}           {# sprintf('%s!', ucfirst($name)) #}
{% set title = name|strtoupper %}            {# $title = strtoupper($name) #}
{{ price * quantity|round }}                 {# $price * round($quantity) #}
{{ (price * quantity)|round }}               {# round($price * $quantity) #}
```

`is empty` and `is not empty` follow the same rule: `{% if user.active and cart is empty %}`.

### Control structures

```jinja
{% if user.isAdmin %} … {% elseif user.isEditor %} … {% else %} … {% endif %}

{% foreach items as item %} … {% endforeach %}
{% foreach items as key => item %} … {% endforeach %}

{% for i = 0; i < 10; i++ %} … {% endfor %}

{% while queue %} … {% endwhile %}

{% switch user.role %}
    {% case 'admin' %}Administrator{% break %}
    {% case 'editor' %}
    {% case 'author' %}Writer{% break %}
    {% default %}Guest
{% endswitch %}
```

`switch` is PHP's: `==` comparison, and a `case` without `{% break %}` runs into the next one.

Conditions are PHP expressions, with a few readable extras:

```jinja
{% if items is empty %}
{% if items is not empty %}
{% if not user.active %}
{% if (age >= 18 and hasPermit) or role == 'admin' %}
```

### Variables

```jinja
{% set total = price * quantity %}
{{ total }}
```

### Template inheritance

```jinja
{# layout.tpl #}
<html>
<head><title>{% block title %}My site{% endblock %}</title></head>
<body>
    {% block content %}{% endblock %}
</body>
</html>
```

```jinja
{# page.tpl #}
{% extends "layout.tpl" %}

{% block title %}Home{% endblock %}

{% block content %}
    <h1>Welcome</h1>
{% endblock %}
```

- The layout receives the same variables as the page.
- A block the page does not define keeps the layout's default content.
- Inheritance works on any number of levels (page → section layout → base layout), and blocks can be nested.
- The deepest template wins: a block defined by the page replaces the one from its layouts.

#### A layout inside a layout

A section layout fills a block of the base layout, and opens a new block inside it for its pages:

```jinja
{# base.tpl: the HTML shell #}
<html>
<head><title>{% block title %}My site{% endblock %}</title></head>
<body>
{% block body %}{% endblock %}
</body>
</html>
```

```jinja
{# admin.tpl: the admin section, inside the shell #}
{% extends "base.tpl" %}

{% block body %}
<nav>Admin menu</nav>
<main>
    {% block content %}{% endblock %}
</main>
{% endblock %}
```

```jinja
{# users.tpl: one admin page #}
{% extends "admin.tpl" %}

{% block title %}Users{% endblock %}

{% block content %}
<h1>Users</h1>
{% endblock %}
```

Rendering `users.tpl` gives the shell, the admin menu, and the page inside `<main>`:

```html
<html>
<head><title>Users</title></head>
<body>
<nav>Admin menu</nav>
<main>
    <h1>Users</h1></main></body>
</html>
```

| A page that extends `admin.tpl`… | Gets |
|---|---|
| defines `content` | the admin menu, with its content in `<main>` |
| defines `title` | its own title; without it, `My site` from the base |
| defines `body` | its own body only: the admin menu and `content` are replaced too |
| defines nothing | the admin menu with an empty `<main>` |

### Includes

```jinja
{% include "partials/header.tpl" %}
```

The included template sees the current variables, including those created with `{% set %}`.

### Comments

```jinja
{# Not in the output #}

{#
    Comments can span lines, and what they contain never runs:
    {% include "old-banner.tpl" %}
#}
```

### Escaped and raw output

Two ways to print, the same as in PHP:

| Template | PHP | Escaped |
|---|---|---|
| `{{ article.html }}` | `echo htmlspecialchars(…)` | yes |
| `{% echo article.html %}` | `echo $article->html` | no |

`{{ … }}` is the safe default. `{% echo … %}` is PHP's own `echo`: use it only for HTML you trust, such as content you sanitized yourself. Filters work with both: `{% echo article.html|strip_tags('<p><a>') %}`.

### Escaping in other contexts

`{{ … }}` escapes for HTML text and quoted attributes. In JavaScript or in a URL, a value needs another escaping, as in plain PHP, Twig or Blade. Declare two small functions once, in your own helpers:

```php
// Any value, as a JavaScript literal that cannot break out of a <script> or an attribute.
function js($value): string
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

// A link typed by a user: only http(s), never javascript:
function safe_url(string $url): string
{
    return preg_match('~^https?://~i', $url) ? $url : '#';
}
```

| Where the value goes | Write |
|---|---|
| HTML text, quoted attribute | `<p title="{{ title }}">{{ name }}</p>` |
| Inside `<script>` | `<script>var user = {% echo js(user) %};</script>` |
| JavaScript in an attribute | `<button onclick="select({{ js(name) }})">` |
| Part of a URL | `<a href="/search?q={{ rawurlencode(q) }}">` |
| A whole URL from a user | `<a href="{{ safe_url(user.website) }}">` |

In `<script>`, use `{% echo %}`: `{{ }}` would turn the JSON quotes into `&quot;`. In an attribute, use `{{ }}`: `{% echo %}` would let the JSON's first `"` close the attribute.

---

## Error messages

Line N of a compiled file is line N of its template, always. When something fails, PurePlate throws an `ErrorException` whose `getFile()` and `getLine()` point at the template that caused it, even inside a layout or an include:

```
PurePlate Error: Undefined variable: username [At: templates/page.tpl:14]
PurePlate Error: Unexpected 'endif', expected 'endforeach' (opened at line 9) [At: templates/page.tpl:12]
PurePlate Error: Unclosed 'if' [At: templates/partials/menu.tpl:3]
PurePlate Error: 'break' outside of a loop or switch [At: templates/page.tpl:20]
```

The original exception is kept in `getPrevious()`. Rendering pays nothing for this: the mapping only runs once an error is thrown.

---

## Cache: dev mode and production

Templates are compiled once to PHP files in the cache directory.

| Mode | Behavior |
|---|---|
| `devMode: true` | Recompiles a template as soon as it changes. Edit, refresh. |
| `devMode: false` (default) | Compiles once, then trusts the cache. No file check at all on each render. |

**In production, clear the cache directory when you deploy new templates.**

```php
$plate = new Engine(__DIR__ . '/templates', true);                              // dev
$plate = new Engine(__DIR__ . '/templates', false, __DIR__ . '/var/cache/plate'); // production
```

---

## Globals

Variables available in every template, without passing them on each render:

```php
$plate = new Engine(__DIR__ . '/templates', false, null, [
    'siteTitle' => 'My Site',
    'version'   => '1.0',
]);
```

A render cannot override a global with a different value: it throws, so a template never silently shows the wrong one.

---

## Plain PHP templates

Files ending in `.php` are rendered as plain PHP, with the same inheritance helpers:

```php
<?php $this->extend('layout.php') ?>

<?php $this->startBlock('content') ?>
    <h1><?= htmlspecialchars($title) ?></h1>
<?php $this->endBlock() ?>
```

```php
<!-- layout.php -->
<main><?= $this->block('content') ?></main>
```

---

## PurePlate or Twig?

| | PurePlate | Twig 3.30 |
|---|---|---|
| Expression language | **PHP** | Twig's own language |
| Speed (benchmark above) | **2.4–2.7× faster** | |
| PHP functions as filters | ✅ all of them, no setup | must be registered |
| Attribute access | compiled to `->` / `[]` | resolved at runtime |
| Errors mapped to template lines | ✅ | ✅ |
| Dependencies | **none** | 3 Symfony packages |
| PHP version | **7.4+** | 8.1+ |
| Size | ~500 lines | ~27,000 lines |
| Sandbox for untrusted templates | ❌ | ✅ |
| Macros, i18n, rich extension ecosystem | ❌ | ✅ |

**Choose PurePlate** when your team knows PHP and writes the templates: templates that read like PHP, run as PHP, faster, with a codebase you can read in full.

**Choose Twig** when templates come from untrusted users (PurePlate templates can call any PHP function, so they must be trusted code), or when you need macros and Twig's extension ecosystem.

---

## It's PHP

**What you read is what runs.** PurePlate never guesses, never resolves anything at runtime, never does something you did not write. Every expression compiles to the one line of PHP you would have written by hand. That single rule is what makes PurePlate fast, predictable, and easy to debug.

PurePlate lightens PHP, it does not hide it. PHP's rules apply in your templates, and that is the point:

- **The dot is `->`.** `user.name` on an array fails, exactly like `$user->name` would. Use `user['name']`.
- **PHP keywords stay keywords.** A bare word PHP reads as a keyword (`list`, `default`, `class`, `new`…) is not a variable. Through an object it works: `page.list`, `user.class`.
- **Errors are PHP errors.** An undefined variable or a call to a missing method fails as in PHP, but points at your template line.
- **Templates are code.** Any PHP function can be called, so never render a template written by an untrusted user.
- **PHP's precedence applies.** Nested ternaries need parentheses, `a ? b : (c ? d : e)`, or PHP 8 refuses them. `and` and `or` bind weaker than `=`: in `{% set %}`, write `&&` and `||`.

On top of PHP, a few template rules:

- `{{ }}` and `{% %}` end on the line where they start; otherwise PurePlate stops with `Unclosed '{{'` at that line. `{# … #}` comments can span lines.
- A tag ends at the first `}}` or `%}`, and a comment is removed even inside a string: build such strings in PHP.
- The syntax stays the template one: PHP 8 named arguments (`f(name: value)`) are not supported.
- `|` is the filter, not PHP's bitwise OR, and a bare word is a variable, not a global constant. Combine flags such as `JSON_HEX_TAG | JSON_HEX_AMP` in a PHP function, like `js()` above.

### Why `user.profile` never calls `getProfile()`

`user.profile` reads the property `$user->profile`. To use a getter, write it: `user.getProfile()`.

Twig does it differently: on `user.profile` it tries an array key, a property, `profile()`, `getProfile()`, `isProfile()`, `hasProfile()`, at runtime, on every access. PurePlate deliberately does not, for four reasons:

1. **Speed.** A direct property read costs almost nothing. A function that looks for a getter first is about **20× more expensive** per access in our measurements: on a page reading 300 values, that alone eats most of the gap between PurePlate and Twig.
2. **No guessing.** When a property and a getter both exist, which one runs? With PurePlate the answer is in the template, not in a lookup order you have to remember.
3. **It stays PHP.** `{% set user.name = 'Fady' %}`, `user.count++`, `user.profile ?? 'none'` all work, because `user.profile` is a real property access, not a function call.
4. **Your tools still work.** Search your project for `getProfile` and you find the templates that use it. Rename the method in your IDE and the template breaks loudly at the right line, instead of silently falling back to something else.

Five more characters, `.getProfile()`, buy all of that.

Prefer short dots in your templates? Give the template what it needs: a simple object with public properties (a view model or DTO), which also keeps your templates independent from your entities. And if a class of yours really wants `user.profile` on a private property, PHP's own `__get()` works out of the box.

---

## Tests

```bash
composer install
vendor/bin/unitester tests
```

---

## License

Mozilla Public License 2.0
