<?php

declare(strict_types=1);

namespace OrpheusNET\BencodeTorrent;

class BencodeTest extends \PHPUnit\Framework\TestCase
{
    public function dataProvider(): array
    {
        return [
            ['i0e', 0],
            ['i-1e', -1],
            ['i20e', 20],
            ['le', []],
            ['d4:item5:valuee', ['item' => 'value']],
            ['0:', ''],
            ['1:a', 'a'],
            ['9:123456789', '123456789']
        ];
    }

    /**
     * @dataProvider dataProvider
     * @param mixed  $expected
     */
    public function testDecodeEncode(string $bencoded_string, $expected): void
    {
        $bencode = new Bencode();
        $bencode->decodeString($bencoded_string);
        $this->assertEquals($expected, $bencode->getData());
        $this->assertEquals($bencoded_string, $bencode->getEncode());
    }

    public function testEmptyDict(): void
    {
        $bencode = new Bencode();
        $bencode->decodeString('de');
        $this->assertEquals([], $bencode->getData());
        $this->assertEquals('le', $bencode->getEncode());
    }

    public function testInvalidDictionaryKey(): void
    {
        $bencode = new Bencode();
        $this->expectException(\RuntimeException::class);
        $bencode->decodeString('di1e5:valuee');
    }

    public function invalidIntegers(): array
    {
        return [['-0'], ['a'], ['1.0']];
    }

    /**
     * @param string $value
     * @dataProvider invalidIntegers
     */
    public function testInvalidInteger(string $value): void
    {
        $bencode = new Bencode();
        $this->expectException(\RuntimeException::class);
        $bencode->decodeString("i{$value}e");
    }

    public function testInvalidString(): void
    {
        $bencode = new Bencode();
        $this->expectException(\RuntimeException::class);
        $bencode->decodeString('i1e0e');
    }
}
