<?php

namespace Tests\Unit;

use App\Models\BuildModel;
use App\Models\Cast\TolerantJsonCast;
use App\Services\JsonColumnRepairService;
use CodeIgniter\DataConverter\DataConverter;
use CodeIgniter\Model;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionClass;

/**
 * @internal
 */
final class TolerantJsonCastTest extends CIUnitTestCase
{
    /** A converter wired the way BuildModel wires its casts. */
    private function converter(string $type = '?json-array'): DataConverter
    {
        return new DataConverter(['doc' => $type], ['json' => TolerantJsonCast::class]);
    }

    private function read(?string $stored, string $type = '?json-array'): mixed
    {
        return $this->converter($type)->fromDataSource(['doc' => $stored])['doc'];
    }

    private function write(mixed $value, string $type = '?json-array'): mixed
    {
        return $this->converter($type)->toDataSource(['doc' => $value])['doc'];
    }

    // ------------------------------------------------------------------ reads

    public function testReadsAPlainDocument(): void
    {
        $this->assertSame(['a' => 1, 'b' => [true, null]], $this->read('{"a": 1, "b": [true, null]}'));
        $this->assertSame([], $this->read('[]'));
    }

    public function testReadsADoubleEncodedDocument(): void
    {
        $doc = ['flow_handoff_id' => 57, 'title' => 'Total off by ₹0.01', 'url' => 'https://x.test/a/b'];

        $this->assertSame($doc, $this->read(json_encode(json_encode($doc))));
        $this->assertSame($doc, $this->read(json_encode(json_encode(json_encode($doc)))), 'triple-encoded');
        $this->assertSame([], $this->read('"[]"'));
    }

    public function testReadsJsonScalarsInsteadOfThrowing(): void
    {
        $this->assertSame('confirm', $this->read('"confirm"'));
        $this->assertSame('auto', $this->read(json_encode(json_encode('auto'))), 'double-encoded string');
        $this->assertSame(5, $this->read('5'));
        $this->assertFalse($this->read('false'));
        $this->assertNull($this->read('null'));
    }

    public function testLeavesStringsThatOnlyLookLikeJsonAlone(): void
    {
        $this->assertSame('[draft] fix totals', $this->read('"[draft] fix totals"'));
        $this->assertSame('{not json}', $this->read('"{not json}"'));
        $this->assertSame('5', $this->read('"5"'), 'a stored "5" stays a string');
    }

    public function testNullColumnReadsAsNull(): void
    {
        $this->assertNull($this->read(null));
    }

    public function testUnwrapStopsAfterTheLimit(): void
    {
        $value = ['deep' => true];
        for ($i = 0; $i < TolerantJsonCast::MAX_UNWRAP + 2; $i++) {
            $value = json_encode($value);
        }

        $this->assertIsString($this->read(json_encode($value)));
    }

    // ----------------------------------------------------------------- writes

    public function testWritesArraysOnce(): void
    {
        $this->assertSame('{"a":1,"path":"app/x.php","name":"₹"}', $this->write(['a' => 1, 'path' => 'app/x.php', 'name' => '₹']));
        $this->assertSame('[]', $this->write([]));
        $this->assertNull($this->write(null));
    }

    public function testWritesScalarsAsJsonScalars(): void
    {
        $this->assertSame('"auto"', $this->write('auto'));
        $this->assertSame('4', $this->write(4));
    }

    public function testPreEncodedDocumentIsNotEncodedAgain(): void
    {
        // A writer that still json_encode()s: the backstop stores the document.
        $this->assertSame('{"a":1}', $this->write(json_encode(['a' => 1])));
        $this->assertSame('[1,2]', $this->write('[1,2]'));
        // A string that is not a JSON document stays a string.
        $this->assertSame('"[draft]"', $this->write('[draft]'));
    }

    public function testRoundTrip(): void
    {
        $doc = ['suites' => ['unit', 'database'], 'meta' => ['retries' => 0]];

        $this->assertSame($doc, $this->read($this->write($doc)));
    }

    public function testNotNullCastStillRefusesNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->read(null, 'json-array');
    }

    // ------------------------------------------------------------ repair text

    public function testRepairedText(): void
    {
        $this->assertSame('{"a":1}', JsonColumnRepairService::repairedText(json_encode(json_encode(['a' => 1]))));
        $this->assertSame('{}', JsonColumnRepairService::repairedText('"{}"'), 'an empty object stays an object');
        $this->assertSame('[]', JsonColumnRepairService::repairedText('"[]"'));
        $this->assertSame('"auto"', JsonColumnRepairService::repairedText(json_encode(json_encode('auto'))));
        $this->assertSame('{"a":1}', JsonColumnRepairService::repairedText(json_encode(json_encode(json_encode(['a' => 1])))));

        $this->assertNull(JsonColumnRepairService::repairedText('{"a":1}'), 'already a document');
        $this->assertNull(JsonColumnRepairService::repairedText('"confirm"'), 'a genuine string');
        $this->assertNull(JsonColumnRepairService::repairedText('"[draft]"'), 'looks like JSON, is not');
        $this->assertNull(JsonColumnRepairService::repairedText('"5"'), 'ambiguous scalar left alone');
    }

    // ------------------------------------------------------------------ guard

    /**
     * Every model with a JSON cast must use the tolerant handler, declare
     * nullable columns as ?json-array, and be covered by the repair command.
     */
    public function testEveryJsonCastModelIsWiredUp(): void
    {
        $repairable = JsonColumnRepairService::MODELS;
        $notNull    = ['App\Models\FlowHandoffsModel' => ['raw_payload']];

        foreach (glob(APPPATH . 'Models/*Model.php') as $file) {
            $class = 'App\\Models\\' . basename($file, '.php');
            $ref   = new ReflectionClass($class);
            if ($ref->isAbstract() || ! $ref->isSubclassOf(Model::class)) {
                continue;
            }

            $casts = $ref->getProperty('casts')->getDefaultValue();
            $json  = array_filter($casts, static fn ($t) => str_contains((string) $t, 'json'));
            if ($json === []) {
                continue;
            }

            $this->assertTrue($ref->isSubclassOf(BuildModel::class), "{$class} has JSON casts but does not extend BuildModel");
            $this->assertContains($class, $repairable, "{$class} is missing from JsonColumnRepairService::MODELS");

            foreach ($json as $field => $type) {
                $expected = in_array($field, $notNull[$class] ?? [], true) ? 'json-array' : '?json-array';
                $this->assertSame($expected, $type, "{$class}::\$casts['{$field}']");
            }
        }
    }
}
