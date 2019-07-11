<?php

namespace datagutten\AudioMetadata\tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;
use AudioMetadata;
use DependencyFailedException;
use FileNotFoundException;
use Exception;

/**
 * @codeCoverageIgnore
 */
abstract class MetadataTestAbstract extends TestCase
{
    public $extension = 'm4a';
    public $empty_file;
    public $test_files_dir;
    public $valid_file;
    public $output_file;
    protected $info = array('artist'=>'No. 4', 'title'=>'Det finnes bare vi', 'tracknumber'=>'9', 'album'=>'Hva na', 'albumartist'=>'No. 4');
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->valid_file = __DIR__.'/sample_data/test.'.$this->extension;
    }

    public function setUp(): void
    {
        //$this->test_files_dir = __DIR__.'/test_files';
        $this->test_files_dir = sys_get_temp_dir().'/test_output';
        if(!file_exists($this->test_files_dir))
            mkdir($this->test_files_dir);
        $this->output_file = $this->test_files_dir.'/output.'.$this->extension;
        if(file_exists($this->output_file))
            unlink($this->output_file);
    }

    /**
     * @throws DependencyFailedException
     */
    function testDependency()
    {
        AudioMetadata::check_dependencies($this->extension);
        $this->assertTrue(true);
    }

    public function testEmptyFile()
    {
        touch($this->output_file);
        $this->expectException(ProcessFailedException::class);
        AudioMetadata::write_metadata($this->output_file, 'test_output.' . $this->extension, $this->info);
    }

    /**
     * @throws Exception
     */
    function testWriteMetadataAndRename()
    {
        $file = AudioMetadata::metadata($this->valid_file, dirname($this->output_file), $this->info);
        $this->assertNotEmpty($file);
        $this->assertFileExists($file);
        $file = AudioMetadata::metadata($this->valid_file, dirname($this->output_file), $this->info);
        unlink($file);
        rmdir(dirname($file));
    }

    /**
     * @throws Exception
     */
    function testWriteMetadataWithCover()
    {
        $file = AudioMetadata::metadata($this->valid_file, dirname($this->output_file), $this->info + ['cover'=>__DIR__.'/sample_data/artwork.jpg']);
        $this->assertNotEmpty($file);
        $this->assertFileExists($file);
        $cover_file = dirname($file).'/'.$this->info['album'].'.jpg';
        $this->assertFileExists($cover_file);
        unlink($cover_file);
        unlink($file);
        rmdir(dirname($file));
    }

    /**
     * @throws FileNotFoundException
     * @throws Exception
     */
    function testReadMetadata()
    {
        $file = AudioMetadata::write_metadata($this->valid_file, $this->output_file, $this->info);
        $metadata = AudioMetadata::read_metadata($file);
        $this->assertEquals($this->info['artist'], $metadata['ARTIST']);
        $this->assertEquals($this->info['title'], $metadata['TITLE']);
        $this->assertEquals($this->info['album'], $metadata['ALBUM']);
        $this->assertEquals($this->info['tracknumber'], $metadata['TRACKNUMBER']);
    }

    /**
     * @throws FileNotFoundException
     */
    function testReadMetadataEmptyFile()
    {
        touch($this->output_file);
        $this->expectException(ProcessFailedException::class);
        AudioMetadata::read_metadata($this->output_file);
    }

    function testReadMetadataMissingFile()
    {
        $this->expectException(FileNotFoundException::class);
        AudioMetadata::read_metadata('missing.'.$this->extension);
    }

    function testWriteMissingFile()
    {
        $this->expectException(FileNotFoundException::class);
        AudioMetadata::write_metadata('missing.'.$this->extension, $this->output_file, $this->info);
    }
    function testWriteInvalidExtension()
    {
        $this->output_file .= '.wav';
        $this->expectException(\InvalidArgumentException::class);
        AudioMetadata::write_metadata($this->output_file, '', $this->info);
    }
    function testInvalidNewFile()
    {
        $this->expectException(\Exception::class);
        AudioMetadata::write_metadata($this->valid_file, '/foo/bar', $this->info);
    }
    public function testArtwork()
    {
        AudioMetadata::write_metadata($this->valid_file, $this->output_file, $this->info,__DIR__.'/sample_data/artwork.jpg');
        $this->addToAssertionCount(1);
    }
    public function testInvalidArtwork()
    {
        $this->expectException(ProcessFailedException::class);
        AudioMetadata::write_metadata($this->valid_file, $this->output_file, $this->info, __DIR__.'/sample_data/invalid.jpg');
    }
    public function testMultiDisc()
    {
        $info = $this->info;
        $info['totaltracks'] = 20;
        $info['volumenumber'] = 2;
        $info['totalvolumes'] = 2;
        //$this->writeMetadata('test', $extension, null);
        $output = AudioMetadata::write_metadata($this->valid_file, $this->output_file, $info);
        $this->assertFileExists($output);
        //$metadata = AudioMetadata::read_metadata($this->output_file);
        //$this->assertEquals(2, $metadata['DISCNUMBER']);
    }

    public function tearDown(): void
    {
        if(file_exists($this->output_file))
            unlink($this->output_file);
        if(file_exists($this->test_files_dir))
            @rmdir($this->test_files_dir);
    }
    public function testCompilation()
    {
        foreach (array(true, false, 'foo') as $value)
        {
            AudioMetadata::write_metadata($this->valid_file, $this->output_file, $this->info + ['compilation'=>$value]);
            unlink($this->output_file);
        }
        $this->assertNotFalse(true);
    }
    public function testInvalidTagValue()
    {
        $this->expectException(ProcessFailedException::class);
        $info = array('title'=>str_repeat('a', 1024*10));
        AudioMetadata::write_metadata($this->valid_file, $this->output_file, $info);
        $info = array('title'=>str_repeat('a', 1024*1000));
        AudioMetadata::write_metadata($this->valid_file, $this->output_file, $info);
    }
}