# PurePlate

PurePlate is a lightweight and versatile template rendering library for native PHP.

## Installation

You can install the library using Composer. Just run the following command:

```bash
composer require michel/pure-plate
```

## Basic Usage

To use the renderer in your project, first create an instance of the `PhpRenderer` class and pass the directory where your templates are located.

```php
use Michel\Renderer\PurePlate;

// Specify the template directory
$templateDir = '/path/to/templates';

// Optional global variables to be passed to all templates
$globals = [
    'siteTitle' => 'My Website',
];

// Create the renderer instance
$renderer = new PurePlate($templateDir, $globals);
```

### Creating a Layout

Create a layout file (e.g., `layout.php`) that represents the common structure of your pages. Use `block()` to define sections that will be replaced by content from child templates.

```php
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $this->block('title'); ?></title>
</head>
<body>
    <div class="container">
        <?php echo $this->block('content'); ?>
    </div>
</body>
</html>
```

### Creating a Template

Create your template file (e.g., `page.php`). Use `extend()` to specify the layout file and `startBlock()` / `endBlock()` to define the content for the blocks.

```php
<?php $this->extend('layout.php'); ?>

<?php $this->startBlock('title'); ?>
    My Page Title
<?php $this->endBlock(); ?>

<?php $this->startBlock('content'); ?>
    <h1>Hello, <?php echo $name; ?>!</h1>
    <p>Welcome to my website.</p>
<?php $this->endBlock(); ?>
```

### Rendering Templates

To render your template, use the `render` method. You can pass an array of variables to be extracted and made available within the template.

```php
echo $renderer->render('page.php', ['name' => 'John']);
```

This will render `page.php`, inject its blocks into `layout.php`, and return the final HTML.

## Contributing

Contributions to the PurePlate library are welcome! If you find any issues or want to suggest enhancements, feel free to open a GitHub issue or submit a pull request.

## License

PurePlate is open-source software released under the MIT License. See the [LICENSE](LICENSE) file for more details.
