<?php

declare(strict_types=1);

/*
 * Copyright (c) Precision Soft
 */

namespace PrecisionSoft\Doctrine\Type\Exception;

use Doctrine\DBAL\Exception as DoctrineDbalException;
use Exception as BaseException;

class Exception extends BaseException implements DoctrineDbalException {}
