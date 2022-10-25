<?php

namespace STS\LaravelUppyCompanion\Contracts;

interface S3ClientContract {
    public function createMultipartUpload($args);
    public function listParts($args);
    public function getCommand($args);
    public function abortMultipartUpload($args);
    public function completeMultipartUpload($args);
}
