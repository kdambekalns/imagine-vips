<?php

/*
 * This file is part of the imagine-vips package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Imagine\Vips;

use Imagine\Exception\InvalidArgumentException;
use Imagine\Exception\NotSupportedException;
use Imagine\Exception\RuntimeException;
use Imagine\Image\AbstractImagine;
use Imagine\Image\BoxInterface;
use Imagine\Image\Metadata\MetadataBag;
use Imagine\Image\Palette\CMYK;
use Imagine\Image\Palette\Color\ColorInterface;
use Imagine\Image\Palette\Grayscale;
use Imagine\Image\Palette\PaletteInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Profile;
use Imagine\Image\VipsProfile;
use Jcupitt\Vips\Exception;
use Jcupitt\Vips\Image as VipsImage;
use Jcupitt\Vips\Interpretation;

/**
 * Imagine implementation using the Vips PHP extension.
 */
class Imagine extends AbstractImagine
{
    /**
     * @throws RuntimeException
     */
    public function __construct()
    {
        if (!extension_loaded('vips')) {
            throw new RuntimeException('Vips not installed');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function open($path)
    {
        $path = $this->checkPath($path);

        try {
            $loadOptions = $this->getLoadOptions(VipsImage::findLoad($path));
            $vips = VipsImage::newFromFile($path, $loadOptions);

            return new Image($vips, self::createPalette($vips), $this->getMetadataReader()->readFile($path));
        } catch (\Exception $e) {
            throw new RuntimeException(sprintf('Unable to open image %s', $path), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create(BoxInterface $size, ColorInterface $color = null)
    {
        $vips = Image::generateImage($size, $color);

        return new Image($vips, self::createPalette($vips), new MetadataBag());
    }

    /**
     * {@inheritdoc}
     */
    public function load($string)
    {
        try {
            $loadOptions = $this->getLoadOptions(VipsImage::findLoadBuffer($string));
            $vips = VipsImage::newFromBuffer($string, '', $loadOptions);

            return new Image($vips, self::createPalette($vips), $this->getMetadataReader()->readData($string));
        } catch (\Exception $e) {
            throw new RuntimeException('Could not load image from string', $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read($resource)
    {
        if (!is_resource($resource)) {
            throw new InvalidArgumentException('Variable does not contain a stream resource');
        }

        $content = stream_get_contents($resource);

        return $this->load($content);
    }

    /**
     * {@inheritdoc}
     */
    public function font($file, $size, ColorInterface $color)
    {
        return new Font(null, $file, $size, $color);
    }

    /**
     * Returns the palette corresponding to an VIPS resource colorspace.
     *
     * @param VipsImage $vips
     *
     * @throws NotSupportedException
     *
     * @return PaletteInterface
     */
    public static function createPalette(VipsImage $vips)
    {
        switch ($vips->interpretation) {
            case Interpretation::RGB:
            case Interpretation::RGB16:
            case Interpretation::SRGB:
                $palette = new RGB();
                break;
            case Interpretation::CMYK:
                $palette = new CMYK();
                break;
            case Interpretation::GREY16:
            case Interpretation::B_W:
                $palette = new Grayscale();
                break;
            default:
                throw new NotSupportedException('Only RGB, CMYK and Grayscale colorspace are currently supported');
        }
        try {
            $profile = $vips->get('icc-profile-data');
            $palette->useProfile(VipsProfile::fromRawData($profile));
        } catch (Exception $e) {
        }

        return $palette;
    }

    protected function getLoadOptions($loader)
    {
        $options = [];
        switch ($loader) {
            case 'VipsForeignLoadJpegFile':
            case 'VipsForeignLoadJpegBuffer':
                $options['autorotate'] = true;
        }

        return $options;
    }
}
