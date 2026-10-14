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
        $this->removeDir($this->cacheDir);
        $this->removeDir($this->tplDir);
    }

    private function removeDir(string $dir): void
    {
        foreach (glob("$dir/*") as $path) {
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    protected function execute(): void
    {
        $this->itRendersComment();
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
        $this->itAcceptsTagsWithoutSpaces();
        $this->itIncludesPartialWithCurrentVariables();
        $this->itPassesContextToLayout();
        $this->itShowsLayoutDefaultBlockContent();
        $this->itSupportsMultiLevelInheritance();
        $this->itAllowsAnyContextKeyName();
        $this->itKeepsSeparateCacheForSameNameTemplates();
        $this->itKeepsNewLinesAfterOutputTags();
        $this->itReportsErrorsAtTheTemplateLine();
        $this->itReportsExceptionsThrownByUserCode();
        $this->itHandlesTernaryExpressions();
        $this->itSupportsNestedBlocksAcrossThreeLevels();
        $this->itKeepsNewLinesWithWindowsLineEndings();
        $this->itRendersTheReadmeExample();
        $this->itRecompilesChangedTemplatesInDevMode();
        $this->itTrustsTheCacheInProduction();
        $this->itHandlesSwitchCase();
        $this->itReportsSwitchMistakes();
        $this->itHandlesStaticAccess();
        $this->itRejectsBreakOutsideLoops();
        $this->itChecksBlocksLikeOtherStructures();
        $this->itProtectsXmlPrologAndBlockNames();
        $this->itIgnoresMultiLineComments();
        $this->itRejectsTagsNotClosedOnTheirLine();
        $this->itAppliesFiltersToTheValueBeforeThem();
        $this->itFillsABlockNestedInAMiddleLayout();
    }

    private function assertTemplateError(string $name, string $tpl, int $line, string $message): void
    {
        file_put_contents($this->tplDir . '/' . $name, $tpl);
        try {
            $this->engine->render($name, ['a' => 1, 't' => true, 'nums' => [1, 2]]);
            $this->fail("$name should throw");
        } catch (ErrorException $e) {
            $this->assertEquals($line, $e->getLine());
            $this->assertStringContains($e->getMessage(), $message);
        }
    }

    public function itRendersComment()
    {
        $tpl = "{#        {% include 'flash_bag.html.twig' %}#}\n{#        {% include 'flash_bag.html.twig' %}#}";
        file_put_contents($this->tplDir . '/comment.html', $tpl);
        $output = $this->engine->render('comment.html', []);
        $this->assertEquals('', trim($output));

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
            $this->assertStringContains($e->getMessage(), 'error.html');
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
        $this->assertStringContains($res1, 'TAGS: 2');

        $res2 = $this->engine->render('is_not_empty.html', ['tags' => []]);
        $this->assertStringContains($res2, 'EMPTY');
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

    public function itAcceptsTagsWithoutSpaces()
    {
        file_put_contents($this->tplDir . '/no_spaces.html', "{%if show%}YES{%endif%}");

        $this->assertEquals("YES", trim($this->engine->render('no_spaces.html', ['show' => true])));
    }

    public function itIncludesPartialWithCurrentVariables()
    {
        file_put_contents($this->tplDir . '/partial.html', "Hi {{ name }} {{ mood }}");
        file_put_contents($this->tplDir . '/with_include.html', "{% set mood = ':)' %}{% include 'partial.html' %}!");

        $output = $this->engine->render('with_include.html', ['name' => 'Fady']);
        $this->assertEquals("Hi Fady :)!", trim($output));
    }

    public function itPassesContextToLayout()
    {
        file_put_contents($this->tplDir . '/layout.html', "<title>{{ title }}</title>{% block content %}{% endblock %}");
        file_put_contents($this->tplDir . '/page.html', "{% extends 'layout.html' %}\n{% block content %}Body{% endblock %}");

        $output = $this->engine->render('page.html', ['title' => 'Home']);
        $this->assertEquals("<title>Home</title>Body", trim($output));
    }

    public function itShowsLayoutDefaultBlockContent()
    {
        file_put_contents($this->tplDir . '/layout_default.html', "{% block title %}Default{% endblock %}|{% block content %}{% endblock %}");
        file_put_contents($this->tplDir . '/page_default.html', "{% extends 'layout_default.html' %}\n{% block content %}Body{% endblock %}");

        $this->assertEquals("Default|Body", trim($this->engine->render('page_default.html')));
    }

    public function itSupportsMultiLevelInheritance()
    {
        file_put_contents($this->tplDir . '/base.html', "[{% block content %}{% endblock %}]");
        file_put_contents($this->tplDir . '/middle.html', "{% extends 'base.html' %}\n{% block content %}middle{% endblock %}");
        file_put_contents($this->tplDir . '/child.html', "{% extends 'middle.html' %}\n{% block content %}child{% endblock %}");

        $this->assertEquals("[child]", trim($this->engine->render('child.html')));
        $this->assertEquals("[middle]", trim($this->engine->render('middle.html')));
    }

    public function itAllowsAnyContextKeyName()
    {
        file_put_contents($this->tplDir . '/keys.html', "{{ filename }} {{ context }} {{ level }}");

        $output = $this->engine->render('keys.html', ['filename' => 'a', 'context' => 'b', 'level' => 'c']);
        $this->assertEquals("a b c", trim($output));
    }

    public function itKeepsSeparateCacheForSameNameTemplates()
    {
        mkdir($this->tplDir . '/admin');
        file_put_contents($this->tplDir . '/same.html', "front");
        file_put_contents($this->tplDir . '/admin/same.html', "admin");

        $engine = new Engine($this->tplDir, false, $this->cacheDir);
        $this->assertEquals("front", trim($engine->render('same.html')));
        $this->assertEquals("admin", trim($engine->render('admin/same.html')));
    }

    public function itKeepsNewLinesAfterOutputTags()
    {
        file_put_contents($this->tplDir . '/lines.txt', "1:{{ a }}\n2:{{ b }}\n3:end");

        $this->assertEquals("1:A\n2:B\n3:end", trim($this->engine->render('lines.txt', ['a' => 'A', 'b' => 'B'])));
    }

    public function itReportsErrorsAtTheTemplateLine()
    {
        $templates = [
            'err_var.html' => "a {{ ok }}\nb {{ ok }}\nc {{ undefinedVar }}",
            'err_if.html' => "1\n2\n{% if undefinedVar %}x{% endif %}",
            'err_syntax.html' => "1\n{{ ok + }}",
            'err_endif.html' => "1\n2\n{% endif %}",
            'err_unclosed.html' => "1\n{% if ok %}",
            'err_mismatch.html' => "1\n{% foreach items as i %}\n{% endif %}",
            'err_blank.html' => "\n\n{{ undefinedVar }}",
            'err_include.html' => "1\n{% include 'err_partial.html' %}",
            'err_partial.html' => "1\n2\n{{ undefinedVar }}",
            'err_child.html' => "{% extends 'err_layout.html' %}\n{% block c %}hi{% endblock %}",
            'err_layout.html' => "1\n{{ undefinedVar }}\n{% block c %}{% endblock %}",
        ];
        foreach ($templates as $name => $tpl) {
            file_put_contents($this->tplDir . '/' . $name, $tpl);
        }

        // rendered template => [template where the error is, line]
        $expected = [
            'err_var.html' => ['err_var.html', 3],
            'err_if.html' => ['err_if.html', 3],
            'err_syntax.html' => ['err_syntax.html', 2],
            'err_endif.html' => ['err_endif.html', 3],
            'err_unclosed.html' => ['err_unclosed.html', 2],
            'err_mismatch.html' => ['err_mismatch.html', 3],
            'err_blank.html' => ['err_blank.html', 3],
            'err_include.html' => ['err_partial.html', 3],
            'err_child.html' => ['err_layout.html', 2],
        ];
        foreach ($expected as $rendered => [$file, $line]) {
            try {
                $this->engine->render($rendered, ['ok' => 1, 'items' => []]);
                $this->fail("$rendered should throw");
            } catch (ErrorException $e) {
                $this->assertEquals($this->tplDir . '/' . $file, $e->getFile());
                $this->assertEquals($line, $e->getLine());
                $this->assertStringContains($e->getMessage(), "$file:$line]");
            }
        }
    }

    public function itReportsExceptionsThrownByUserCode()
    {
        file_put_contents($this->tplDir . '/err_user_code.html', "1\n{{ obj.boom() }}");
        $obj = new class {
            public function boom()
            {
                throw new \LogicException('boom!');
            }
        };

        try {
            $this->engine->render('err_user_code.html', ['obj' => $obj]);
            $this->fail("err_user_code.html should throw");
        } catch (ErrorException $e) {
            $this->assertEquals($this->tplDir . '/err_user_code.html', $e->getFile());
            $this->assertEquals(2, $e->getLine());
            $this->assertInstanceOf(\LogicException::class, $e->getPrevious());
        }
    }

    public function itHandlesTernaryExpressions()
    {
        $tpl = "1:{{ toto == 'toto' ? variable : '' }}
2:{{ toto == 'titi' ? variable : '' }}
3:{{ user.name == 'fady' ? user.name : 'x' }}
4:{{ toto ?: 'empty' }}
5:{{ missing ?? 'default' }}
6:{{ (toto == 'toto') ? 'a' : 'b' }}
7:{{ toto == 'toto' ? strtoupper(variable) : '' }}";
        file_put_contents($this->tplDir . '/ternary.html', $tpl);

        $output = $this->engine->render('ternary.html', [
            'toto' => 'toto',
            'variable' => 'val',
            'user' => (object)['name' => 'fady'],
        ]);
        $this->assertEquals("1:val\n2:\n3:fady\n4:toto\n5:default\n6:a\n7:VAL", trim($output));
    }

    public function itSupportsNestedBlocksAcrossThreeLevels()
    {
        file_put_contents($this->tplDir . '/nested_base.html', '<base>{% block body %}[{% block title %}T-base{% endblock %}|{% block content %}C-base{% endblock %}]{% endblock %}<footer>{% block footer %}F-base{% endblock %}</footer></base>');
        file_put_contents($this->tplDir . '/nested_middle.html', "{% extends 'nested_base.html' %}\n{% block title %}T-middle{% endblock %}\n{% block footer %}F-middle{% endblock %}");
        file_put_contents($this->tplDir . '/nested_child.html', "{% extends 'nested_middle.html' %}\n{% block title %}T-child {{ name }}{% endblock %}");

        $this->assertEquals('<base>[T-child Fady|C-base]<footer>F-middle</footer></base>', trim($this->engine->render('nested_child.html', ['name' => 'Fady'])));
        $this->assertEquals('<base>[T-middle|C-base]<footer>F-middle</footer></base>', trim($this->engine->render('nested_middle.html')));
        $this->assertEquals('<base>[T-base|C-base]<footer>F-base</footer></base>', trim($this->engine->render('nested_base.html')));
    }

    public function itKeepsNewLinesWithWindowsLineEndings()
    {
        file_put_contents($this->tplDir . '/crlf.txt', "1:{{ a }}\r\n2:{{ b }}\r\n3:end");

        $output = $this->engine->render('crlf.txt', ['a' => 'A', 'b' => 'B']);
        $this->assertEquals("1:A\n2:B\n3:end", trim($output));
    }

    public function itRendersTheReadmeExample()
    {
        file_put_contents($this->tplDir . '/readme_layout.tpl', '<title>{% block title %}My site{% endblock %}</title>{% block content %}{% endblock %}');
        file_put_contents($this->tplDir . '/readme_page.tpl', '{% extends "readme_layout.tpl" %}

{% block content %}
    <h1>Hello, {{ user.name|strtoupper }}!</h1>

    {% if items is not empty %}
        <ul>
        {% foreach items as item %}
            <li>{{ item.title }} — {{ item.price|number_format(2, \',\', \' \') }} €</li>
        {% endforeach %}
        </ul>
    {% endif %}
{% endblock %}');

        $output = $this->engine->render('readme_page.tpl', [
            'user' => (object)['name' => 'fady'],
            'items' => [
                (object)['title' => 'Item A', 'price' => 19.90],
                (object)['title' => 'Item B', 'price' => 42.00],
            ],
        ]);
        $this->assertStringContains($output, '<title>My site</title>');
        $this->assertStringContains($output, '<h1>Hello, FADY!</h1>');
        $this->assertStringContains($output, '<li>Item A — 19,90 €</li>');
        $this->assertStringContains($output, '<li>Item B — 42,00 €</li>');
    }

    public function itRecompilesChangedTemplatesInDevMode()
    {
        $path = $this->tplDir . '/dev_reload.html';
        file_put_contents($path, "v1");
        $this->assertEquals("v1", $this->engine->render('dev_reload.html'));

        file_put_contents($path, "v2");
        touch($path, time() + 10);
        $this->assertEquals("v2", $this->engine->render('dev_reload.html'));
    }

    public function itTrustsTheCacheInProduction()
    {
        $path = $this->tplDir . '/prod_cache.html';
        $engine = new Engine($this->tplDir, false, $this->cacheDir);
        file_put_contents($path, "v1");
        $this->assertEquals("v1", $engine->render('prod_cache.html'));

        file_put_contents($path, "v2");
        touch($path, time() + 10);
        $this->assertEquals("v1", $engine->render('prod_cache.html'));
    }

    public function itHandlesSwitchCase()
    {
        $tpl = "{% switch user.role %}
    {% case 'admin' %}Admin{% break %}
    {% case 'editor' %}
    {% case 'author' %}Writer{% break %}
    {% default %}Guest
{% endswitch %}";
        file_put_contents($this->tplDir . '/switch.html', $tpl);

        $roles = ['admin' => 'Admin', 'editor' => 'Writer', 'author' => 'Writer', 'other' => 'Guest'];
        foreach ($roles as $role => $expected) {
            $output = $this->engine->render('switch.html', ['user' => (object)['role' => $role]]);
            $this->assertEquals($expected, trim($output));
        }

        $nested = "{% switch a %}{% case 1 %}{% switch b %}{% case 2 %}one-two{% break %}{% default %}one-other{% endswitch %}{% break %}{% default %}other{% endswitch %}";
        file_put_contents($this->tplDir . '/switch_nested.html', $nested);
        $this->assertEquals("one-two", trim($this->engine->render('switch_nested.html', ['a' => 1, 'b' => 2])));
        $this->assertEquals("one-other", trim($this->engine->render('switch_nested.html', ['a' => 1, 'b' => 3])));
        $this->assertEquals("other", trim($this->engine->render('switch_nested.html', ['a' => 9, 'b' => 2])));
    }

    public function itHandlesStaticAccess()
    {
        $tpl = "1:{{ DateTimeInterface::ATOM }}
2:{{ \\DateTime::class }}
3:{{ DateTime::createFromFormat('Y-m-d', day).format('d/m/Y') }}
4:{{ format == DateTimeInterface::ATOM ? 'atom' : 'other' }}
5:{{ DateTimeInterface::RSS|strlen }}";
        file_put_contents($this->tplDir . '/static.html', $tpl);

        $output = $this->engine->render('static.html', ['day' => '2026-10-14', 'format' => \DateTimeInterface::ATOM]);
        $this->assertEquals("1:Y-m-d\\TH:i:sP\n2:DateTime\n3:14/10/2026\n4:atom\n5:" . strlen(\DateTimeInterface::RSS), trim($output));
    }

    public function itReportsSwitchMistakes()
    {
        $this->assertTemplateError('switch_no_case.html', "1\n{% switch a %}\n{% endswitch %}", 3, "'switch' without 'case' (opened at line 2)");
        $this->assertTemplateError('switch_orphan_case.html', "1\n{% case 1 %}", 2, "Unexpected 'case' outside of a 'switch'");
        $this->assertTemplateError('switch_unclosed.html', "1\n{% switch a %}\n{% case 1 %}x", 2, "Unclosed 'switch'");
        $this->assertTemplateError('switch_wrong_end.html', "{% switch a %}\n{% case 1 %}x\n{% endif %}", 3, "expected 'endswitch'");
    }

    public function itRejectsBreakOutsideLoops()
    {
        $this->assertTemplateError('break_alone.html', "a\n{% break %}", 2, "'break' outside of a loop or switch");
        $this->assertTemplateError('continue_alone.html', "a\n{% if t %}{% continue %}{% endif %}", 2, "'continue' outside of a loop or switch");
        $this->assertTemplateError('break_too_deep.html', "{% foreach nums as n %}\n{% break 2 %}\n{% endforeach %}", 2, "'break 2' needs 2 nested loops or switch");

        $tpl = "{% foreach nums as n %}{% if n == 2 %}{% break %}{% endif %}{{ n }}{% endforeach %}|"
            . "{% foreach nums as n %}{% foreach nums as m %}{% break 2 %}{% endforeach %}{% endforeach %}ok";
        file_put_contents($this->tplDir . '/break_ok.html', $tpl);
        $this->assertEquals("1|ok", trim($this->engine->render('break_ok.html', ['nums' => [1, 2, 3]])));
    }

    public function itChecksBlocksLikeOtherStructures()
    {
        $this->assertTemplateError('block_unclosed.html', "a\n{% block x %}b", 2, "Unclosed 'block'");
        $this->assertTemplateError('block_crossing.html', "{% block x %}\n{% if t %}in\n{% endblock %}\n{% endif %}", 3, "Unexpected 'endblock', expected 'endif' (opened at line 2)");
        $this->assertTemplateError('block_orphan_end.html', "a\n{% endblock %}", 2, "Unexpected 'endblock' (missing opening tag)");
    }

    public function itProtectsXmlPrologAndBlockNames()
    {
        file_put_contents($this->tplDir . '/feed.xml', '<?xml version="1.0"?><r>{{ a }}</r>');
        $this->assertEquals('<?xml version="1.0"?><r>abc</r>', trim($this->engine->render('feed.xml', ['a' => 'abc'])));

        file_put_contents($this->tplDir . '/quote_block.html', "{% block \"it's\" %}quoted{% endblock %}");
        $this->assertEquals('quoted', trim($this->engine->render('quote_block.html')));
    }

    public function itIgnoresMultiLineComments()
    {
        $tpl = "a\n{#\n{{ undefinedVar }}\n{% include 'nope.html' %}\n#}\nb {# one #} c";
        file_put_contents($this->tplDir . '/multiline_comment.html', $tpl);
        $this->assertEquals("a\n\n\n\n\nb  c", trim($this->engine->render('multiline_comment.html')));

        // Line numbers still match the template after a multi-line comment.
        $this->assertTemplateError('comment_then_error.html', "{# 1\n2\n3 #}\n{{ undefinedVar }}", 4, 'undefinedVar');
    }

    public function itRejectsTagsNotClosedOnTheirLine()
    {
        $this->assertTemplateError('open_output.html', "1\n{{ a\n}}", 2, "Unclosed '{{'");
        $this->assertTemplateError('open_statement.html', "{% if t\n%}x{% endif %}", 1, "Unclosed '{%'");
        $this->assertTemplateError('open_comment.html', "1\n2 {# never closed", 2, "Unclosed '{#'");

        file_put_contents($this->tplDir . '/literal_braces.html', "{{ '{{' }} {{ '{%' }}");
        $this->assertEquals('{{ {%', trim($this->engine->render('literal_braces.html')));
    }

    public function itAppliesFiltersToTheValueBeforeThem()
    {
        $tpl = "1:{% if f and e is empty %}WRONG{% else %}OK{% endif %}.
2:{% if t or a is empty %}OK{% else %}WRONG{% endif %}.
3:{% if t and e is not empty %}WRONG{% else %}OK{% endif %}.
4:{% set up = a|strtoupper %}{{ up }}
5:{% set n = items|count %}{{ n }}
6:{{ sprintf('%s-%s', a|strtoupper, b) }}
7:{{ str_repeat(a|strlen, 2) }}
8:{{ isAdmin ? name|strtoupper : 'guest' }}
9:{{ implode(',', [a|strtoupper, b]) }}
10:{{ items|count > 1 ? 'many' : 'one' }}
11:{{ name|trim|strtoupper }}
12:{{ price * qty|round }}
13:{{ (price * qty)|round }}
14:{{ -price|abs }}
15:{{ not e is empty ? 'full' : 'empty' }}
16:{{ items[1]|strval }}
17:{{ number_format(price * qty, 1)|strlen }}
18:{{ a ?? 'x'|strtoupper }}
19:{{ user.getName()|ucfirst }}";
        file_put_contents($this->tplDir . '/filter_scope.html', $tpl);

        $user = new class {
            public function getName()
            {
                return 'fady';
            }
        };
        $output = $this->engine->render('filter_scope.html', [
            'a' => 'abc', 'b' => 'def', 't' => true, 'f' => false, 'e' => [], 'name' => ' fady ',
            'price' => 2, 'qty' => 1.6, 'isAdmin' => true, 'items' => [1, 2, 3], 'user' => $user,
        ]);
        $expected = "1:OK.\n2:OK.\n3:OK.\n4:ABC\n5:3\n6:ABC-def\n7:33\n8: FADY \n9:ABC,def\n10:many\n11:FADY\n"
            . "12:4\n13:3\n14:-2\n15:empty\n16:2\n17:3\n18:abc\n19:Fady";
        $this->assertEquals($expected, trim($output));
    }

    public function itFillsABlockNestedInAMiddleLayout()
    {
        // The middle layout fills "body" and opens a new block "content" inside it, for its children.
        file_put_contents($this->tplDir . '/sub_base.html', "[{% block body %}{% endblock %}]");
        file_put_contents($this->tplDir . '/sub_middle.html', "{% extends 'sub_base.html' %}\n{% block body %}\nmiddle\n{% block content %}\n{% endblock %}\n{% endblock %}");
        file_put_contents($this->tplDir . '/sub_child.html', "{% extends 'sub_middle.html' %}\n{% block content %}child{% endblock %}");
        file_put_contents($this->tplDir . '/sub_child_body.html', "{% extends 'sub_middle.html' %}\n{% block body %}own body{% endblock %}");
        file_put_contents($this->tplDir . '/sub_child_empty.html', "{% extends 'sub_middle.html' %}");

        $this->assertEquals("[middle\nchild]", trim($this->engine->render('sub_child.html')));
        $this->assertEquals("[own body]", trim($this->engine->render('sub_child_body.html')));
        $this->assertEquals("[middle]", trim($this->engine->render('sub_child_empty.html')));
        $this->assertEquals("[middle]", trim($this->engine->render('sub_middle.html')));
    }
}
