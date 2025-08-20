<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmbroideryEstimator extends Component
{
    use WithFileUploads;

    public $design;
    public $previewImage;
    public $result = null;
    public $machineSpeed = 800; // stitches per minute
    public $pricePerThousand = 1.5; // cost per 1k stitches
    public $baseFee = 5;
    public $uploadedFilePath = null; // Track uploaded file for cleanup

    public function estimate()
    {
        $this->validate([
            'design' => 'required|image|mimes:jpg,jpeg,png,svg|max:5120',
        ]);

        try {
            // Store the uploaded file temporarily in S3
            $tempPath = 'temp/embroidery/' . Str::uuid() . '.' . $this->design->getClientOriginalExtension();
            $this->uploadedFilePath = $this->design->storeAs('', $tempPath, 's3');

            // Get the file content from S3
            $fileContent = Storage::disk('s3')->get($this->uploadedFilePath);

            // Create image from file content
            $manager = new ImageManager(new Driver());
            $image = $manager->read($fileContent);

            // Get resolution - use default if not available
            $resolution = $image->resolution();
            $dpi = 72; // Default DPI
            if ($resolution && $resolution->x() && $resolution->y()) {
                $dpi = ($resolution->x() + $resolution->y()) / 2;
            }

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
                    $color = $shrinkedImage->pickColor($x, $y);
                    if ((($color->red()->toInt() + $color->green()->toInt() + $color->blue()->toInt()) / 3) < 250) {
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

        } catch (\Exception $e) {
            $this->addError('design', 'Error processing image: ' . $e->getMessage());
        } finally {
            // Clean up temporary file
            $this->cleanupTempFile();
        }
    }

    public function updatedDesign()
    {
        // Clean up previous temp file when new file is selected
        $this->cleanupTempFile();
        $this->result = null;
        $this->previewImage = null;
    }

    protected function cleanupTempFile()
    {
        if ($this->uploadedFilePath && Storage::disk('s3')->exists($this->uploadedFilePath)) {
            Storage::disk('s3')->delete($this->uploadedFilePath);
            $this->uploadedFilePath = null;
        }
    }

    public function mount()
    {
        // Ensure temp directory structure exists (S3 will create it automatically)
    }

    public function __destruct()
    {
        // Clean up temp file when component is destroyed
        $this->cleanupTempFile();
    }

    public function render()
    {
        return view('livewire.embroidery-estimator');
    }
}
