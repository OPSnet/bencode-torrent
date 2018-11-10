<?php

namespace ApolloRIP\BencodeTorrent;

class BencodeTorrentTest extends \PHPUnit\Framework\TestCase {
    public function testLoadTorrent() {
        $bencode = new BencodeTorrent();
        try {
            $bencode->decodeFile(__DIR__.'/data/test_1.torrent');
        }
        catch (\Exception $exc) {
            $this->fail('Decode should not have thrown exception');
        }
        $data = $bencode->getData();
        $this->assertEquals(
            'https://localhost:34000/4f9587fbcb06fe09165e4f84d35d0403/announce',
            $data['announce']
        );
        $this->assertEquals(
            'https://localhost:8080/torrents.php?id=2&torrentid=2',
            $data['comment']
        );
        $this->assertEquals('uTorrent/3.4.2', $data['created by']);
        $this->assertEquals(1425699508, $data['creation date']);
        $this->assertEquals('UTF-8', $data['encoding']);
        $this->assertArrayHasKey('info', $data);
        $this->assertCount(11, $data['info']['files']);
        $files = [
            [
                'length' => 12310347,
                'path' => ['02 Should have known better.mp3']
            ],
            [
                'length' => 12197480,
                'path' => ['09 John My Beloved.mp3']
            ],
            [
                'length' => 11367829,
                'path' => ['07 The Only Thing.mp3']
            ],
            [
                'length' => 11360526,
                'path' => ['11 Blue Bucket of Gold.mp3']
            ],
            [
                'length' => 11175567,
                'path' => ['06 Fourth of July.mp3']
            ],
            [
                'length' => 9584196,
                'path' => ['01 Death with Dignity.mp3']
            ],
            [
                'length' => 8871591,
                'path' => ['03 All of me wants all of you.mp3']
            ],
            [
                'length' => 7942661,
                'path' => ['04 Drawn to the Blood.mp3']
            ],
            [
                'length' => 7789055,
                'path' => ['08 Carrie & Lowell.mp3']
            ],
            [
                'length' => 6438044,
                'path' => ['10 No shade in the shadow of the cross.mp3']
            ],
            [
                'length' => 5878964,
                'path' => ['05 Eugene.mp3']
            ]
        ];
        $this->assertEquals($files, $data['info']['files']);
        $this->assertEquals(
            'Sufjan Stevens - Carrie & Lowell (2015) [MP3 320]',
            $data['info']['name']
        );
        $this->assertEquals(
            'Sufjan Stevens - Carrie & Lowell (2015) [MP3 320]',
            $bencode->getName()
        );
        $this->assertEquals(16020, strlen($data['info']['pieces']));
        $this->assertEquals(1, $data['info']['private']);
        $this->assertEquals('APL', $data['info']['source']);
        $this->assertStringEqualsFile(__DIR__.'/data/test_1.torrent', $bencode->getEncode());
        $this->assertEquals(104916260, $bencode->getSize());
        $this->assertTrue($bencode->isPrivate());
        $bencode->decodeString($bencode->getEncode());
        $this->assertStringEqualsFile(__DIR__.'/data/test_1.torrent', $bencode->getEncode());
        $this->assertEquals('1f830103427029a88dd5fde85be74e622ee07951', $bencode->getInfoHash());
        $this->assertEquals($bencode->getInfoHash(), unpack('H*', $bencode->getHexInfoHash())[1]);
        $this->assertFalse($bencode->hasEncryptedFiles());
        $file_list = [
            'total_size' => 104916260,
            'files' => [
                [
                    'size' => 9584196,
                    'path' => '01 Death with Dignity.mp3'
                ],
                [
                    'size' => 12310347,
                    'path' => '02 Should have known better.mp3'
                ],
                [
                    'size' => 8871591,
                    'path' => '03 All of me wants all of you.mp3'
                ],
                [
                    'size' => 7942661,
                    'path' => '04 Drawn to the Blood.mp3'
                ],
                [
                    'size' => 5878964,
                    'path' => '05 Eugene.mp3'
                ],
                [
                    'size' => 11175567,
                    'path' => '06 Fourth of July.mp3'
                ],
                [
                    'size' => 11367829,
                    'path' => '07 The Only Thing.mp3'
                ],
                [
                    'size' => 7789055,
                    'path' => '08 Carrie & Lowell.mp3'
                ],
                [
                    'size' => 12197480,
                    'path' => '09 John My Beloved.mp3'
                ],
                [
                    'size' => 6438044,
                    'path' => '10 No shade in the shadow of the cross.mp3'
                ],
                [
                    'size' => 11360526,
                    'path' => '11 Blue Bucket of Gold.mp3'
                ]
            ]
        ];
        $this->assertEquals($file_list, $bencode->getFileList());
    }

    public function testSetData() {
        $data = [
            'encoding' => 'UTF8',
            'info' => [
                'name' => 'test',
                'length' => 1213134,
                'pieces' => 'fake pieces string'
            ]
        ];

        $bencode = new BencodeTorrent();
        $bencode->setData($data);

        $data = $bencode->getData();
        $this->assertEquals($data, $bencode->getData());
        $this->assertEquals('test', $bencode->getName());
        $this->assertFalse($bencode->isPrivate());
        $this->assertEquals(1213134, $bencode->getSize());
        $this->assertEquals(
            [
                'total_size' => 1213134,
                'files' => [
                    [
                        'path' => 'test',
                        'size' => 1213134
                    ]
                ]
            ],
            $bencode->getFileList()
        );
    }

    public function testEmptyDictionary() {
        $expected = [
            'encoding' => 'UTF8',
            'info' => [
                'name' => 'test',
                'length' => 1213134,
                'pieces' => 'fake pieces string',
            ],
            'test' => ''
        ];
        $bencode = new BencodeTorrent();
        $bencode->decodeString(
            'd8:encoding4:UTF84:infod4:name4:test6:lengthi1213134e'.
            '6:pieces18:fake pieces stringe4:test0:e'
        );
        $this->assertEquals($expected, $bencode->getData());
    }

    public function testClean() {
        $data = [
            'encoding' => 'UTF8',
            'info' => [
                'file-duration' => '',
                'file-media' => '',
                'files' => [],
                'name' => 'test',
                'name.utf8' => 'test',
                'name.utf-8' => 'test',
                'length' => 12345,
                'md5sum' => 12345,
                'piece length' => 12345,
                'pieces' => '',
                'private' => 1,
                'profiles' => '',
                'sha1' => 1,
                'source' => 'APL',
                'unique' => 1,
                'test' => 'delete me',
                'x_cross_seed' => 1
            ],
            'test' => 'delete me'
        ];

        $bencode = new BencodeTorrent();
        $bencode->setData($data);
        $this->assertTrue($bencode->clean());
        unset($data['test']);
        unset($data['info']['test']);
        $this->assertEquals($data, $bencode->getData());
    }

    public function testSetPrivate() {
        $data = [
            'encoding' => 'UTF8',
            'info' => [
                'name' => 'test',
                'length' => 1213134,
                'pieces' => 'fake pieces string'
            ]
        ];
        $bencode = new BencodeTorrent();
        $bencode->setData($data);
        $this->assertFalse($bencode->isPrivate());
        $actual = $bencode->getData();
        $this->assertNotContains('private', $actual['info']);
        $this->assertTrue($bencode->makePrivate());
        $this->assertTrue($bencode->isPrivate());
        $actual = $bencode->getData();
        $this->assertEquals(1, $actual['info']['private']);
    }

    public function testSetAlreadyPrivate() {
        $data = [
            'encoding' => 'UTF8',
            'info' => [
                'name' => 'test',
                'private' => 1
            ]
        ];
        $bencode = new BencodeTorrent();
        $bencode->setData($data);
        $this->assertTrue($bencode->isPrivate());
        $this->assertFalse($bencode->makePrivate());
    }

    public function testSetSource() {
        $data = [
            'encoding' => 'UTF8',
            'info' => [
                'name' => 'test',
                'private' => 1,
                'unique' => 1,
                'x_cross_seed' => 'true'
            ]
        ];
        $bencode = new BencodeTorrent();
        $bencode->setData($data);
        $this->assertTrue($bencode->setSource('APL'));
        $actual = $bencode->getData();
        $this->assertEquals('APL', $actual['info']['source']);
        $this->assertNotContains('unique', $actual['info']);
        $this->assertNotContains('x_cross_seed', $actual['info']);
    }

    public function testSetAlreadySourced() {
        $data = [
            'encoding' => 'UTF8',
            'info' => [
                'name' => 'test',
                'private' => 1,
                'source' => 'APL',
                'unique' => 1,
                'x_cross_seed' => 'true'
            ]
        ];

        $bencode = new BencodeTorrent();
        $bencode->setData($data);
        $this->assertFalse($bencode->setSource('APL'));
        $this->assertEquals($data, $bencode->getData());
    }

    public function testSetDifferentSource() {
        $data = [
            'encoding' => 'UTF8',
            'info' => [
                'name' => 'test',
                'private' => 1,
                'source' => 'RED',
                'unique' => 1,
                'x_cross_seed' => 'true'
            ]
        ];
        $bencode = new BencodeTorrent();
        $bencode->setData($data);
        $this->assertTrue($bencode->setSource('APL'));
        $actual = $bencode->getData();
        $this->assertEquals('APL', $actual['info']['source']);
        $this->assertNotContains('unique', $actual['info']);
        $this->assertNotContains('x_cross_seed', $actual['info']);
    }

    public function testSetValue() {
        $data = [
            'encoding' => 'UTF8',
            'info' => [
                'files' => [
                    0 => [
                        'length' => 1234,
                        'path' => ['01 Test.mp3'],
                        'path.utf-8' => ['01 Test??.mp3']
                    ],
                    1 => [
                        'length' => 2345,
                        'path' => ['03 Test!!.mp3'],
                        'path.utf-8' => ['03 Test.mp3']
                    ]
                ],
                'name' => 'test',
                'private' => 1
            ]
        ];
        $bencode = new BencodeTorrent();
        $bencode->setData($data);
        $set = ['announce' => 'http://localhost:8080/announce',
                'info.name' => 'test2',
                'info.private' => 0,
                'info.files.0.path.0' => '02 Test.mp3',
                'comment' => 'http://localhost:8080/torrents.php?id=1'];
        $bencode->setValue($set);
        $expected = [
            'announce' => 'http://localhost:8080/announce',
            'comment' => 'http://localhost:8080/torrents.php?id=1',
            'encoding' => 'UTF8',
            'info' => [
                'files' => [
                    0 => [
                        'length' => 1234,
                        'path' => ['02 Test.mp3'],
                        'path.utf-8' => ['01 Test??.mp3']
                    ],
                    1 => [
                        'length' => 2345,
                        'path' => ['03 Test!!.mp3'],
                        'path.utf-8' => ['03 Test.mp3']
                    ]
                ],
                'name' => 'test2',
                'private' => 0
            ]
        ];
        $this->assertEquals($expected, $bencode->getData());
        $this->assertEquals(
            ['.mp3 s1234s 01 Test??.mp3 ÷', '.mp3 s2345s 03 Test.mp3 ÷'],
            $bencode->getGazelleFileList()
        );
        $this->assertTrue($bencode->hasFiles());
    }

    public function testGetUtf8Name() {
        $data = [
            'encoding' => 'UTF8',
            'info' => [
                'length' => 12345,
                'name' => 'test',
                'name.utf-8' => 'test!!',
                'private' => 1
            ]
        ];
        $bencode = new BencodeTorrent();
        $bencode->setData($data);
        $this->assertEquals('test!!', $bencode->getName());
        $this->assertEquals(12345, $bencode->getSize());
        $this->assertEquals(
            [
                'total_size' => 12345,
                'files' => [
                    [
                        'path' => 'test!!',
                        'size' => 12345
                    ]
                ]
            ],
            $bencode->getFileList()
        );
        $this->assertEquals(['. s12345s test!! ÷'], $bencode->getGazelleFileList());
    }

    public function testIso88591Name() {
        $data = [
            'info' => [
                'length' => 1234,
                'name' => utf8_decode('(test.,!?)óÉ')
            ]
        ];
        $bencode = new BencodeTorrent();
        $bencode->setData($data);
        $this->assertEquals(['.,!?)óÉ s1234s (test.,!?)óÉ ÷'], $bencode->getGazelleFileList());
        $this->assertFalse($bencode->hasFiles());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testInvalidSet() {
        $data = ['encoding' => 'UTF8', 'announce' => 'http://localhost:8080/announce'];
        $bencode = new BencodeTorrent();
        $bencode->setData($data);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testInvalidSetValue() {
        $data = [
            'encoding' => 'UTF8',
            'info' => [
                'name' => 'test',
                'length' => 1213134,
                'pieces' => 'fake pieces string'
            ]
        ];

        $bencode = new BencodeTorrent();
        $bencode->setData($data);
        $bencode->setValue(['info' => []]);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetEncodeNoData() {
        $bencode = new BencodeTorrent();
        $bencode->getEncode();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testInvalidPath() {
        $data = [
            'encoding' => 'UTF8',
            'info' => [
                'files' => [
                    0 => [
                        'length' => 1234,
                        'path' => ['']
                    ]
                ],
                'name' => 'test',
                'length' => 1213134,
                'pieces' => 'fake pieces string'
            ]
        ];
        $bencode = new BencodeTorrent();
        $bencode->setData($data);
    }
}
