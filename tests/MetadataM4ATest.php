<?php
namespace datagutten\AudioMetadata\tests;
use AudioMetadata;
use FileNotFoundException;
use InvalidArgumentException;

/**
 * @codeCoverageIgnore
 */
class MetadataM4ATest extends MetadataTestAbstract
{
    public $extension = 'm4a';

    /**
     * @throws FileNotFoundException
     */
    public function testReadMetadata()
    {
        $this->expectException(InvalidArgumentException::class);
        parent::testReadMetadata();
    }
    public function testReadMetadataEmptyFile()
    {
        touch($this->output_file);
        $this->expectException(InvalidArgumentException::class);
        AudioMetadata::read_metadata($this->output_file);
    }
}