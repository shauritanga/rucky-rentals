<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GeneratePwaIcons extends Command
{
    protected $signature   = 'pwa:icons';
    protected $description = 'Generate PWA app icons (192×192 and 512×512 PNG) using GD';

    public function handle(): int
    {
        $outDir = public_path('icons');

        if (! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $icon512 = $this->createIcon(512);
        imagepng($icon512, $outDir . '/icon-512.png', 9);
        imagedestroy($icon512);
        $this->info('Generated public/icons/icon-512.png');

        $icon192 = $this->createIcon(192);
        imagepng($icon192, $outDir . '/icon-192.png', 9);
        imagedestroy($icon192);
        $this->info('Generated public/icons/icon-192.png');

        return self::SUCCESS;
    }

    private function createIcon(int $size): \GdImage
    {
        $img = imagecreatetruecolor($size, $size);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        // Colors
        $darkBg = imagecolorallocate($img, 10,  10,  20);   // #0a0a14
        $indigo = imagecolorallocate($img, 99,  102, 241);  // #6366f1
        $white  = imagecolorallocate($img, 255, 255, 255);

        // Background
        imagefill($img, 0, 0, $darkBg);

        // Rounded square (indigo)
        $pad = (int)($size * 0.09);
        $rad = (int)($size * 0.18);
        $x1  = $pad;
        $y1  = $pad;
        $x2  = $size - $pad;
        $y2  = $size - $pad;
        $this->filledRoundedRect($img, $x1, $y1, $x2, $y2, $rad, $indigo);

        // Letter "R"
        $this->drawR($img, $size, $white, $indigo);

        return $img;
    }

    private function filledRoundedRect(\GdImage $img, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        imagefilledrectangle($img, $x1 + $r, $y1,      $x2 - $r, $y2,      $color);
        imagefilledrectangle($img, $x1,      $y1 + $r, $x2,      $y2 - $r, $color);
        imagefilledellipse($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
    }

    private function drawR(\GdImage $img, int $size, int $fg, int $bg): void
    {
        // Bounding box for the letter (relative to icon size)
        $lx   = (int)($size * 0.27);   // stem left
        $ty   = (int)($size * 0.22);   // top
        $by   = (int)($size * 0.78);   // bottom
        $rMax = (int)($size * 0.72);   // rightmost x

        $lh  = $by - $ty;                       // total height
        $sw  = max(2, (int)($lh * 0.16));       // stroke width
        $midY = $ty + (int)($lh * 0.50);        // bowl bottom / leg top

        // ── 1. Vertical stem ──────────────────────────────────────────────────
        imagefilledrectangle($img, $lx, $ty, $lx + $sw, $by, $fg);

        // ── 2. Upper bowl ─────────────────────────────────────────────────────
        $bowlW  = $rMax - ($lx + $sw);
        $bowlH  = $midY - $ty;
        $bowlCx = $lx + $sw + (int)($bowlW / 2);
        $bowlCy = $ty + (int)($bowlH / 2);

        // Outer filled arc (right half)
        imagefilledarc($img, $bowlCx, $bowlCy, $bowlW, $bowlH, 270, 90, $fg, IMG_ARC_PIE);
        // Fill gap between stem and arc
        imagefilledrectangle($img, $lx + $sw, $ty, $bowlCx, $midY, $fg);

        // Inner cutout to hollow the bowl
        $inW = $bowlW - $sw * 2;
        $inH = $bowlH - $sw * 2;
        if ($inW > 2 && $inH > 2) {
            imagefilledarc($img, $bowlCx, $bowlCy, $inW, $inH, 270, 90, $bg, IMG_ARC_PIE);
            imagefilledrectangle($img, $lx + $sw + 1, $ty + $sw, $bowlCx, $midY - $sw, $bg);
        }

        // ── 3. Top and middle horizontal bars ─────────────────────────────────
        imagefilledrectangle($img, $lx, $ty,         $lx + $sw + (int)($bowlW * 0.55), $ty + $sw,   $fg);
        imagefilledrectangle($img, $lx, $midY - $sw, $lx + $sw + (int)($bowlW * 0.55), $midY,       $fg);

        // ── 4. Diagonal leg ───────────────────────────────────────────────────
        $legX1 = $lx + $sw + (int)($bowlW * 0.28);
        $legY1 = $midY;
        $legX2 = $rMax;
        $legY2 = $by;

        // Build a parallelogram polygon for a thick diagonal
        $dx  = $legX2 - $legX1;
        $dy  = $legY2 - $legY1;
        $len = sqrt($dx * $dx + $dy * $dy);
        if ($len > 0) {
            $nx = (int)((-$dy / $len) * $sw * 0.8);
            $ny = (int)(($dx  / $len) * $sw * 0.8);
            imagefilledpolygon($img, [
                $legX1 + $nx, $legY1 + $ny,
                $legX1 - $nx, $legY1 - $ny,
                $legX2 - $nx, $legY2 - $ny,
                $legX2 + $nx, $legY2 + $ny,
            ], $fg);
        }
    }
}
