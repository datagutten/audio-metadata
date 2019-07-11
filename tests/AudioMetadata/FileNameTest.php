<?php

namespace datagutten\AudioMetadata\tests\AudioMetadata;

use datagutten\AudioMetadata\AudioMetadata;
use PHPUnit\Framework\TestCase;

/**
 * @codeCoverageIgnore
 */
class FileNameTest extends TestCase
{
    public function testTitleWithTrack()
    {
        $info = array('title'=>'Dancing Like This', 'tracknumber'=>3);
        $file = AudioMetadata::build_file_name($info);
        $this->assertEquals('03 Dancing Like This', $file);
    }
    public function testTitleCompilation()
    {
        $info = array('title'=>'Dancing Like This',
            'artist'=>'Hajk',
            'tracknumber'=>3,
            'compilation'=>true);
        $file = AudioMetadata::build_file_name($info);
        $this->assertEquals('03 Hajk - Dancing Like This', $file);
    }
    public function testMultipleVolumes()
    {
        $info = array('title'=>'Dancing Like This',
            'artist'=>'Hajk',
            'tracknumber'=>3,
            'totalvolumes'=>2,
            'volumenumber'=>1);
        $file = AudioMetadata::build_file_name($info);
        $this->assertEquals('01 03 Dancing Like This', $file);
    }
    public function testExtension()
    {
        $info = array('title'=>'Dancing Like This', 'tracknumber'=>3);
        $file = AudioMetadata::build_file_name($info, 'flac');
        $this->assertEquals('03 Dancing Like This.flac', $file);
    }
}