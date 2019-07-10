<?php

use PHPUnit\Framework\TestCase;

/**
 * @codeCoverageIgnore
 */
class MetadataTest extends TestCase
{
    public $info = array('artist'=>'No. 4', 'title'=>'Det finnes bare vi', 'track'=>'9', 'album'=>'Hva na', 'albumartist'=>'No. 4');
    public function testFileName()
    {
        $file = AudioMetadata::build_file_name($this->info);
        $folder = AudioMetadata::build_directory_name($this->info);

        $this->assertEquals('09 Det finnes bare vi', $file);
        $this->assertEquals('No. 4 - Hva na', $folder);
    }
    public function testFileNameCompilation()
    {
        $file = AudioMetadata::build_file_name($this->info + array('compilation'=>true));
        $folder = AudioMetadata::build_directory_name($this->info + array('compilation'=>true));

        $this->assertEquals('09 No. 4 - Det finnes bare vi', $file);
        $this->assertEquals('No. 4 - Hva na', $folder);
    }

    public function testFolderName()
    {
        $file = AudioMetadata::build_file_name(array('artist'=>'No. 4', 'title'=>'Det finnes bare vi', 'track'=>'9'));
        $this->assertEquals('09 Det finnes bare vi', $file);
    }

    /**
     * @throws FileNotFoundException
     */
    public function testWriteMetadataFlac()
    {
        $dependcheck = new dependcheck();
        try {
            $dependcheck->depend('metaflac');
        }
        catch (DependencyFailedException $e)
        {
            $this->markTestSkipped('metaflac is not installed');
        }
        try {
            AudioMetadata::metaflac(__DIR__ . '/test.flac', 'metadata.flac', $this->info);
        }
        catch (FileNotFoundException $e)
        {
            $this->markTestSkipped('test.flac missing');
        }
        $info = AudioMetadata::read_metadata('metadata.flac');
        $this->assertIsArray($info);
        $this->assertEquals($info['TITLE'], $this->info['title']);
        $this->assertEquals($info['ARTIST'], $this->info['artist']);
        $this->assertEquals($info['ALBUM'], $this->info['album']);
        unlink('metadata.flac');
    }

}