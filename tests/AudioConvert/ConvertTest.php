<?php


namespace datagutten\AudioMetadata\tests\AudioConvert;


use datagutten\AudioMetadata\AudioConvert;
use DependencyFailedException;
use FileNotFoundException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class convertTest extends TestCase
{
    protected $sample_dir;
    protected $output_dir;
    public $output_file;
    public function setUp(): void
    {
        $this->sample_dir = __DIR__.'/../sample_data';
        $this->output_dir = sys_get_temp_dir().'/test_output';
        if(!file_exists($this->output_dir))
            mkdir($this->output_dir);
        /*$this->output_file = $this->test_files_dir.'/output.'.$this->extension;
        if(file_exists($this->output_file))
            unlink($this->output_file);*/
    }

    /**
     * @throws DependencyFailedException
     */
    public function testDependencies()
    {
        AudioConvert::check_dependencies();
        $this->assertNotFalse(true);
    }

    /**
     * @throws FileNotFoundException
     * @requires PHPUnit >=8
     */
    public function testConvertFlacToWav()
    {
        $file = AudioConvert::convert_to_wav($this->sample_dir.'/test.flac', $this->output_dir.'/converted.wav');
        $this->assertStringContainsString('.wav', $file);
        $this->assertFileExists($file);
        unlink($file);
    }

    public function testConvertEmptyToWav()
    {
        $this->output_file = $this->output_dir.'/empty.flac';
        touch($this->output_file);
        $this->expectException(ProcessFailedException::class);
        AudioConvert::convert_to_wav($this->output_file);
    }
    public function testConvertEmptyToFlac()
    {
        $this->output_file = $this->output_dir.'/empty.m4a';
        touch($this->output_file);
        $this->expectException(ProcessFailedException::class);
        AudioConvert::convert_to_flac($this->output_file);
    }

    /**
     * @throws FileNotFoundException
     * @requires PHPUnit >=8
     */
    public function testConvertM4AToFlac()
    {
        $file = AudioConvert::convert_to_flac($this->sample_dir.'/test.m4a', $this->output_dir.'/converted.flac');
        $this->assertStringContainsString('.flac', $file);
        $this->assertFileExists($file);
        unlink($file);
    }

    public function testConvertToExistingFlac()
    {
        AudioConvert::convert_to_flac($this->sample_dir.'/test.m4a', $this->output_dir.'/converted.flac');
        $file = AudioConvert::convert_to_flac($this->sample_dir.'/test.m4a', $this->output_dir.'/converted.flac');
        $this->assertFileExists($file);
        unlink($file);
    }

    public function testConvertToFlacTempFile()
    {
        $tmp_file = sys_get_temp_dir() . '/test.m4a.wav';
        touch($tmp_file);
        $file = AudioConvert::convert_to_flac($this->sample_dir.'/test.m4a', $this->output_dir.'/converted.flac');
        $this->assertFileNotExists($tmp_file);
        $this->assertFileExists($file);
        unlink($file);
    }

    public function testConvertToExistingWav()
    {
        AudioConvert::convert_to_wav($this->sample_dir.'/test.m4a', $this->output_dir.'/converted.wav');
        $file = AudioConvert::convert_to_wav($this->sample_dir.'/test.m4a', $this->output_dir.'/converted.wav');
        $this->assertFileExists($file);
        unlink($file);
    }
    public function testConvertMissingToFlac()
    {
        $this->expectException(FileNotFoundException::class);
        AudioConvert::convert_to_flac('invalid.m4a');
    }
    public function testConvertMissingToWav()
    {
        $this->expectException(FileNotFoundException::class);
        AudioConvert::convert_to_wav('invalid.m4a');
    }

    public function tearDown(): void
    {
        if(file_exists($this->output_file))
            unlink($this->output_file);
        rmdir($this->output_dir);
    }
}