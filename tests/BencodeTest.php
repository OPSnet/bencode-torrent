<?php

namespace OrpheusNET\BencodeTorrent;

class BencodeTest extends \PHPUnit\Framework\TestCase
{
    public function dataProvider()
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
     * @param string $bencoded_string
     * @param mixed  $expected
     */
    public function testDecodeEncode($bencoded_string, $expected)
    {
        $bencode = new Bencode();
        $bencode->decodeString($bencoded_string);
        $this->assertEquals($expected, $bencode->getData());
        $this->assertEquals($bencoded_string, $bencode->getEncode());
    }

    public function testEmptyDict()
    {
        $bencode = new Bencode();
        $bencode->decodeString('de');
        $this->assertEquals([], $bencode->getData());
        $this->assertEquals('le', $bencode->getEncode());
    }

    public function testInvalidDictionaryKey()
    {
        $bencode = new Bencode();
        $this->expectException(\RuntimeException::class);
        $bencode->decodeString('di1e5:valuee');
    }

    public function invalidIntegers()
    {
        return [['-0'], ['a'], ['1.0']];
    }

    /**
     * @param string $value
     * @dataProvider invalidIntegers
     */
    public function testInvalidInteger(string $value)
    {
        $bencode = new Bencode();
        $this->expectException(\RuntimeException::class);
        $bencode->decodeString("i{$value}e");
    }

    public function testInvalidString()
    {
        $bencode = new Bencode();
        $this->expectException(\RuntimeException::class);
        $bencode->decodeString('i1e0e');
    }
}
