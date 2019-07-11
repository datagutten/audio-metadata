<?php

namespace datagutten\AudioMetadata\tests\AudioMetadata;

use datagutten\AudioMetadata\AudioMetadata;
use PHPUnit\Framework\TestCase;

/**
 * @codeCoverageIgnore
 */
class DirectoryNameTest extends TestCase
{
    function testAlbumWithArtist()
    {
        $info = array('albumartist'=>'Hajk', 'album'=>'Drama');
        $folder = AudioMetadata::build_directory_name($info);
        $this->assertEquals('Hajk - Drama', $folder);
    }
    function testAlbumWithArtistAndAlbumYear()
    {
        $info = array('albumartist'=>'Hajk', 'album'=>'Drama', 'albumyear'=>2019);
        $folder = AudioMetadata::build_directory_name($info);
        $this->assertEquals('Hajk - Drama (2019)', $folder);
    }
    function testAlbumWithArtistAndYear()
    {
        $info = array('albumartist'=>'Hajk', 'album'=>'Drama', 'year'=>2019);
        $folder = AudioMetadata::build_directory_name($info);
        $this->assertEquals('Hajk - Drama (2019)', $folder);
    }
    function testAlbumWithoutArtist()
    {
        $info = array('album'=>'Drama', 'year'=>2019);
        $folder = AudioMetadata::build_directory_name($info);
        $this->assertEquals('Drama (2019)', $folder);
    }
    function testAlbumWithExtension()
    {
        $info = array('albumartist'=>'Hajk', 'album'=>'Drama', 'year'=>2019);
        $folder = AudioMetadata::build_directory_name($info, 'flac');
        $this->assertEquals('Hajk - Drama (2019) FLAC', $folder);
    }
}