<?php

namespace ApolloRip\BencodeTorrent;

use PHPUnit\Framework\TestCase;

class BencodeTorrentTest extends TestCase {
	public function testLoadTorrent() {
		$bencode = new BencodeTorrent();
		try {
			$bencode->decodeFile(__DIR__.'/data/test_1.torrent');
		}
		catch (\Exception $exc) {
			$this->fail('Decode should not have thrown exception');
		}
		$data = $bencode->getData();
		$this->assertEquals('https://localhost:34000/4f9587fbcb06fe09165e4f84d35d0403/announce', $data['announce']);
		$this->assertEquals('https://localhost:8080/torrents.php?id=2&torrentid=2', $data['comment']);
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
		$this->assertEquals('Sufjan Stevens - Carrie & Lowell (2015) [MP3 320]', $data['info']['name']);
		$this->assertEquals('Sufjan Stevens - Carrie & Lowell (2015) [MP3 320]', $bencode->getName());
		$this->assertEquals(16020, strlen($data['info']['pieces']));
		$this->assertEquals(1, $data['info']['private']);
		$this->assertEquals('APL', $data['info']['source']);
		$this->assertStringEqualsFile(__DIR__.'/data/test_1.torrent', $bencode->getEncode());
	}

	public function testSetData() {

	}
}