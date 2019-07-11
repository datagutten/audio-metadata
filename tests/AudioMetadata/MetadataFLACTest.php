<?php

namespace datagutten\AudioMetadata\tests\AudioMetadata;

use AudioMetadata;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * @codeCoverageIgnore
 */
class MetadataFLACTest extends MetadataTestAbstract
{
    public $extension = 'flac';
    public function testEmptyFileNoRemove()
    {
        touch($this->output_file);
        $this->expectException(ProcessFailedException::class);
        //AudioMetadata::write_metadata($this->output_file, 'test_output.' . $this->extension, $this->info);
        AudioMetadata::metaflac($this->output_file, 'test_output.' . $this->extension, $this->info, null, false);
    }

}