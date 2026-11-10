<?php

namespace App\Actions\Collection;

use App\Enums\ImageSide;
use App\Models\CollectionItem;
use App\Models\ItemImage;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Save an uploaded card photo as WebP, with a small thumbnail for grids.
 *
 * Photos arrive already shrunk by the browser, but this still caps their size so
 * uploads from anywhere else stay reasonable. Anything GD cannot read is stored
 * as uploaded, without a thumbnail.
 */
class StoreCardPhoto
{
    /** Longest edge of the stored photo, enough to read a card's fine print. */
    public const int MAX_EDGE = 2000;

    /** Longest edge of the grid thumbnail: about twice a tile's width on an iPad. */
    public const int THUMB_EDGE = 480;

    private const int PHOTO_QUALITY = 82;

    private const int THUMB_QUALITY = 75;

    public function handle(CollectionItem $item, UploadedFile $file, ImageSide $side, int $position): ItemImage
    {
        $directory = 'cards/'.$item->user_id;
        $source = $this->decode($file);

        if ($source === null) {
            return $item->images()->create([
                'path' => $file->store($directory, ItemImage::DISK),
                'side' => $side,
                'position' => $position,
            ]);
        }

        $name = $directory.'/'.Str::random(40);
        $disk = Storage::disk(ItemImage::DISK);

        $disk->put($name.'.webp', $this->encode($source, self::MAX_EDGE, self::PHOTO_QUALITY));
        $disk->put($name.'-thumb.webp', $this->encode($source, self::THUMB_EDGE, self::THUMB_QUALITY));

        return $item->images()->create([
            'path' => $name.'.webp',
            'thumb_path' => $name.'-thumb.webp',
            'side' => $side,
            'position' => $position,
        ]);
    }

    /**
     * Read the upload into an upright, true-colour image, or null when GD cannot read it.
     */
    private function decode(UploadedFile $file): ?GdImage
    {
        $contents = $file->get();
        $image = is_string($contents) ? @imagecreatefromstring($contents) : false;

        if (! $image instanceof GdImage) {
            return null;
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $this->applyOrientation($image, $file);
    }

    /**
     * Turn camera JPEGs upright: GD ignores the EXIF orientation flag that phones rely on.
     */
    private function applyOrientation(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => 270,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    /**
     * Scale the image down to fit maxEdge (never up) and encode it as WebP.
     */
    private function encode(GdImage $image, int $maxEdge, int $quality): string
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxEdge / max($width, $height));

        $output = $scale < 1
            ? imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), IMG_BICUBIC)
            : $image;

        if (! $output instanceof GdImage) {
            $output = $image;
        }

        imagesavealpha($output, true);

        ob_start();
        imagewebp($output, null, $quality);

        return (string) ob_get_clean();
    }
}
