<?php

namespace Test\Michel\PurePlate;

use ErrorException;
use Michel\PurePlate\Engine;
use Michel\UniTester\TestCase;

final class PurePlateTest extends TestCase
{
    private Engine $engine;
    private string $cacheDir = __DIR__ . '/cache';
    private string $tplDir = __DIR__ . '/tpl';

    protected function setUp(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir);
        }
        if (!is_dir($this->tplDir)) {
            mkdir($this->tplDir);
        }

        $this->engine = new Engine($this->tplDir, true, $this->cacheDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            array_map('unlink', glob("$this->cacheDir/*.*"));
            rmdir($this->cacheDir);
        }
        if (is_dir($this->tplDir)) {
            array_map('unlink', glob("$this->tplDir/*.*"));
            rmdir($this->tplDir);
        }
    }

    protected function execute(): void
    {
        $this->itRendersSimpleVariables();
        $this->itHandlesFiltersWithArguments();
        $this->itExecutesLogicBlocks();
        $this->itMapsErrorsToOriginalTemplateLine();
        $this->itHandlesComplexLogicAndNotOperator();
        $this->itHandlesForeachWithObjectsAndArrays();
        $this->itHandlesComplexNestedLogic();
        $this->itHandlesNestedForeachAndIf();
        $this->itHandlesIsNotEmptySyntax();
        $this->itHandlesNativeArraySyntax();
    }

    /** @test */
    public function itRendersSimpleVariables()
    {
        $tpl = "Hello {{ user.name }}!";
        file_put_contents($this->tplDir . '/test.html', $tpl);

        $output = $this->engine->render('test.html', ['user' => (object)['name' => 'Michel']]);
        $this->assertEquals("Hello Michel!", trim($output));
    }

    /** @test */
    public function itHandlesFiltersWithArguments()
    {
        $tpl = "Total: {{ price | round(2) }} €";
        file_put_contents($this->tplDir . '/filter.html', $tpl);

        $output = $this->engine->render('filter.html', ['price' => 12.556]);
        $this->assertEquals("Total: 12.56 €", trim($output));
    }

    /** @test */
    public function itExecutesLogicBlocks()
    {
        $tpl = "{% if show %}YES{% else %}NO{% endif %}";
        file_put_contents($this->tplDir . '/logic.html', $tpl);

        $this->assertEquals("YES", trim($this->engine->render('logic.html', ['show' => true])));
        $this->assertEquals("NO", trim($this->engine->render('logic.html', ['show' => false])));
    }

    /** @test */
    public function itMapsErrorsToOriginalTemplateLine()
    {
        $tpl = "Line 1\nLine 2\n{{ undefined_var.property }}";
        file_put_contents($this->tplDir . '/error.html', $tpl);

        try {
            $this->engine->render('error.html', []);
            $this->fail("L'exception aurait dû être lancée.");
        } catch (ErrorException $e) {
            // Vérification du mapping de ligne magique /*L:3;F:...*/
            $this->assertEquals(3, $e->getLine());
            $this->assertStringContains($e->getFile(), 'error.html');
        }
    }

    /** @test */
    public function itHandlesComplexLogicAndNotOperator()
    {
        $tpl = "{% if not user.is_active %}Inactif{% endif %}";
        file_put_contents($this->tplDir . '/not.html', $tpl);

        $output = $this->engine->render('not.html', ['user' => (object)['is_active' => false]]);
        $this->assertEquals("Inactif", trim($output));
    }

    /** @test */
    public function itHandlesForeachWithObjectsAndArrays()
    {
        $tpl = "<ul>{% foreach users as user %}<li>{{ user.name }} ({{ user.role }})</li>{% endforeach %}</ul>";
        file_put_contents($this->tplDir . '/foreach.html', $tpl);

        $data = [
            'users' => [
                (object)['name' => 'Alice', 'role' => 'Admin'],
                (object)['name' => 'Bob', 'role' => 'User'],
            ]
        ];

        $output = $this->engine->render('foreach.html', $data);
        $this->assertStringContains($output, 'Alice (Admin)');
        $this->assertStringContains($output, 'Bob (User)');
    }

    /** @test */
    public function itHandlesComplexNestedLogic()
    {
        $tpl = "{% if (user.age >= 18 and user.has_permit) or user.role == 'admin' %}ACCESS GRANTED{% endif %}";
        file_put_contents($this->tplDir . '/complex_logic.html', $tpl);

        $res1 = $this->engine->render('complex_logic.html', [
            'user' => (object)['age' => 17, 'has_permit' => false, 'role' => 'admin']
        ]);
        $this->assertEquals("ACCESS GRANTED", trim($res1));

        $res2 = $this->engine->render('complex_logic.html', [
            'user' => (object)['age' => 20, 'has_permit' => true, 'role' => 'user']
        ]);
        $this->assertEquals("ACCESS GRANTED", trim($res2));

        $res3 = $this->engine->render('complex_logic.html', [
            'user' => (object)['age' => 16, 'has_permit' => true, 'role' => 'user']
        ]);
        $this->assertEquals("", trim($res3));
    }

    /** @test */
    public function itHandlesNestedForeachAndIf()
    {
        $tpl = "{% foreach categories as cat %}
                {{ cat.name }}:
                {% foreach cat.items as item %}
                    {% if item.price > 10 %}{{ item.name }}{% endif %}
                {% endforeach %}
            {% endforeach %}";

        file_put_contents($this->tplDir . '/nested.html', $tpl);

        $data = [
            'categories' => [
                (object)[
                    'name' => 'Tech',
                    'items' => [
                        (object)['name' => 'Mouse', 'price' => 15],
                        (object)['name' => 'Pad', 'price' => 5]
                    ]
                ]
            ]
        ];

        $output = $this->engine->render('nested.html', $data);
        $this->assertStringContains($output, 'Tech:');
        $this->assertStringContains($output, 'Mouse');
    }

    /** @test */
    public function itHandlesIsNotEmptySyntax()
    {
        $tpl = "{% if tags is not empty %}TAGS: {{ tags | count }}{% else %}EMPTY{% endif %}";
        file_put_contents($this->tplDir . '/is_not_empty.html', $tpl);

        $res1 = $this->engine->render('is_not_empty.html', ['tags' => ['php', 'lexer']]);
        $this->assertStringContains('TAGS: 2', $res1);

        $res2 = $this->engine->render('is_not_empty.html', ['tags' => []]);
        $this->assertStringContains('EMPTY', $res2);
    }

    /** @test */
    public function itHandlesNativeArraySyntax()
    {
        $tpl = "First: {{ tags[0] }}
            Key: {{ config['env'] }}
            Count: {{ tags | count }}
            Dynamic: {% set idx = 1 %}{{ tags[idx] }}";

        file_put_contents($this->tplDir . '/arrays_native.html', $tpl);

        $data = [
            'tags' => ['PHP', 'Lexer', 'Fast'],
            'config' => ['env' => 'production']
        ];

        $output = $this->engine->render('arrays_native.html', $data);

        $this->assertStringContains($output, 'First: PHP');
        $this->assertStringContains($output, 'Key: production');
        $this->assertStringContains($output, 'Count: 3');
        $this->assertStringContains($output, 'Dynamic: Lexer');
    }
}
