<?php

namespace datagutten\AudioMetadata\tests\AudioMetadata;

use datagutten\AudioMetadata\AudioMetadata;
use Exception;
use FileNotFoundException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @codeCoverageIgnore
 */
class MetadataTest extends TestCase
{
    protected $valid_file;
    protected $info = array('artist'=>'No. 4', 'title'=>'Det finnes bare vi', 'tracknumber'=>'9', 'album'=>'Hva na', 'albumartist'=>'No. 4');
    protected $supported_extensions = array('flac', 'm4a');
    protected $output_file = 'output.foo.bar';

    public function setUp(): void
    {
        //$this->output_file = $this->test_files_dir.'/output.'.$this->extension;
        if(file_exists($this->output_file))
            unlink($this->output_file);
    }

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


    public function writeMetadata($file, $extension, $compilation = null, $artwork = null)
    {
        $this->info['compilation'] = $compilation;
        $file = AudioMetadata::write_metadata(__DIR__.'/'.$file.'.' . $extension, 'test_output.' . $extension, $this->info, $artwork);
        $this->assertEquals('test_output.' . $extension, $file);
    }

    /**
     * @throws Exception
     */
    public function testMissingArtwork()
    {
        $this->expectException(FileNotFoundException::class);
        AudioMetadata::write_metadata(null, null, null, 'missing.jpg');
    }

    /*public function testTotalTracks()
    {
        $extensions = array('flac', 'm4a');
        foreach ($extensions as $extension)
        {
            $this->info['totaltracks'] = 20;
            printf("Total tracks: %s\n", $extension);
            $this->writeMetadata('test', $extension, false);
        }
    }*/
    public function testRenameMissingFile()
    {
        $this->expectException(FileNotFoundException::class);
        AudioMetadata::metadata('missing.flac', null, null);
    }

    public function testWriteUnsupportedExtension()
    {
        $this->expectException(InvalidArgumentException::class);
        AudioMetadata::write_metadata('invalid.wav', '', array());
    }
    public function testReadUnsupportedExtension()
    {
        touch('invalid.wav');
        $this->expectException(InvalidArgumentException::class);
        AudioMetadata::read_metadata('invalid.wav');
        unlink('invalid.wav');
    }

}