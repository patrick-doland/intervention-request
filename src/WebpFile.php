<?php
/**
 * Copyright (c) 2018  Ambroise Maupate
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace AM\InterventionRequest;

use Symfony\Component\HttpFoundation\File\File;

/**
 * @package AM\InterventionRequest
 * @deprecated Use NextGenFile to support all next-generation formats (WEBP, AVIF, …)
 */
class WebpFile extends NextGenFile
{
    /**
     * @var bool
     */
    private $isWebp = false;

    /**
     * @inheritDoc
     */
    public function __construct($path, $checkPath = true)
    {
        if (preg_match('#\.(jpe?g|gif|png)\.webp$#', $path) > 0) {
            $this->isWebp = true;
            $this->isNextGen = true;
            $this->nextGenMimeType = 'image/webp';
            $this->requestedPath = $path;
            $this->requestedFile = new File($path, false);
            $realPath = preg_replace('#\.webp$#', '', $path);
            parent::__construct($realPath ?? '', $checkPath);
        } else {
            parent::__construct($path, $checkPath);
        }
    }

    /**
     * @return bool
     */
    public function isWebp(): bool
    {
        return $this->isWebp;
    }
}
