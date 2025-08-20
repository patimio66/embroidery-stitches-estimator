<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Intervention\Image\Drivers\Gd\Driver;   // or Imagick if you prefer
use Intervention\Image\ImageManager;

class EmbroideryEstimator extends Component
{
    use WithFileUploads;

    public $design;
    public $previewImage;
    public $result = null;
    public $machineSpeed = 800; // stitches per minute
    public $pricePerThousand = 1.5; // cost per 1k stitches
    public $baseFee = 5;

    public function estimate()
    {
        $this->validate([
            'design' => 'required|image|mimes:jpg,jpeg,png,svg|max:5120',
        ]);

        $manager = new ImageManager(new Driver());
        $image = $manager->read($this->design->getRealPath());
        $resolution = $image->resolution();
        $dpi = ($resolution->x() + $resolution->y()) / 2;

        $trimmedImage = $image->trim();
        $width = $trimmedImage->width();
        $height = $trimmedImage->height();

        $width_cm = ($width / $dpi) * 2.54;
        $height_cm = ($height / $dpi) * 2.54;

        $area_cm2 = $width_cm * $height_cm;
        $area_in2 = $area_cm2 / 6.4516;

        // Complexity detection (coverage %)
        $shrinkedImage = $trimmedImage->scale(width: 200); // shrink for performance
        $this->previewImage = $shrinkedImage->toWebp()->toDataUri();
        $width = $shrinkedImage->width();
        $height = $shrinkedImage->height();
        $darkPixels = 0;
        $totalPixels = $width * $height;
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $color = $shrinkedImage->pickColor($x, $y); // Color object
                if ((($color->red()->toInt() + $color->green()->toInt() + $color->blue()->toInt()) / 3) < 250) { // since grayscale, R=G=B
                    $darkPixels++;
                }
            }
        }
        $coverage = $darkPixels / $totalPixels;


        // Adjust stitch multiplier
        if ($coverage < 0.2) {
            $multiplier = 1500; // light design
        } elseif ($coverage < 0.6) {
            $multiplier = 2000; // medium
        } else {
            $multiplier = 2500; // heavy
        }

        $stitch_estimate = round(($area_in2 * $coverage) * $multiplier);

        // Production time
        $minutes = round($stitch_estimate / $this->machineSpeed, 1);

        // Thread usage
        $thread_m = round(($stitch_estimate / 1000) * 6, 1);

        // Price
        $price = $this->baseFee + ($stitch_estimate / 1000) * $this->pricePerThousand;

        $this->result = [
            'width_cm' => round($width_cm, 2),
            'height_cm' => round($height_cm, 2),
            'area_cm2' => round($area_cm2, 2),
            'coverage' => round($coverage * 100, 1) . '%',
            'estimated_stitches' => $stitch_estimate,
            'production_time' => $minutes . ' min',
            'thread_usage' => $thread_m . ' m',
            'price' => '$' . number_format($price, 2),
        ];
    }

    public function render()
    {
        return view('livewire.embroidery-estimator');
    }
}
