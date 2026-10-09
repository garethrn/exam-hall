<?php
/**
 * Draw a certificate onto an uploaded PNG template.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Certificate rendering.
 */
class EH_Certificate {

    /**
     * Whether PHP can composite text onto a PNG.
     *
     * @return bool
     */
    public static function can_render() {
        if (!function_exists('imagecreatefrompng') || !function_exists('imagepng')) {
            return false;
        }
        if (self::has_freetype()) {
            return is_readable(self::font_path(true));
        }
        return function_exists('imagestring');
    }

    /**
     * True when GD can draw TrueType text.
     *
     * @return bool
     */
    /**
     * Whether certificates are drawn with the bundled TrueType font.
     *
     * @return bool
     */
    public static function uses_truetype() {
        return self::has_freetype();
    }

    private static function has_freetype() {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        if (!function_exists('imagettftext') || !is_readable(self::font_path(true))) {
            $ok = false;
            return false;
        }
        $probe = imagecreatetruecolor(8, 8);
        $color = imagecolorallocate($probe, 0, 0, 0);
        $drawn = @imagettftext($probe, 8, 0, 0, 8, $color, self::font_path(true), 'A');
        imagedestroy($probe);
        $ok = $drawn !== false;
        return $ok;
    }

    /**
     * Default field positions, as percentages of the template.
     *
     * @return array
     */
    public static function default_layout() {
        return array(
            'name' => array('x' => 50, 'y' => 46, 'size' => 4.2, 'color' => '#111827', 'align' => 'center'),
            'subject' => array('x' => 50, 'y' => 58, 'size' => 2.6, 'color' => '#374151', 'align' => 'center'),
            'grade' => array('x' => 50, 'y' => 68, 'size' => 3.2, 'color' => '#111827', 'align' => 'center'),
            'date' => array('x' => 50, 'y' => 78, 'size' => 2.2, 'color' => '#4b5563', 'align' => 'center'),
        );
    }

    /**
     * Saved layout, with defaults for anything missing.
     *
     * @return array
     */
    public static function layout() {
        $saved = json_decode((string) get_option('eh_certificate_layout'), true);
        $layout = self::default_layout();
        if (!is_array($saved)) {
            return $layout;
        }
        foreach ($layout as $key => $field) {
            if (!isset($saved[$key]) || !is_array($saved[$key])) {
                continue;
            }
            $layout[$key] = self::normalize_field($saved[$key], $field);
        }
        return $layout;
    }

    /**
     * Read the four field controls from a settings form.
     *
     * @return array
     */
    public static function layout_from_post() {
        $layout = self::default_layout();
        foreach ($layout as $key => $field) {
            $posted = array(
                'x' => isset($_POST['cert_' . $key . '_x']) ? wp_unslash($_POST['cert_' . $key . '_x']) : $field['x'],
                'y' => isset($_POST['cert_' . $key . '_y']) ? wp_unslash($_POST['cert_' . $key . '_y']) : $field['y'],
                'size' => isset($_POST['cert_' . $key . '_size']) ? wp_unslash($_POST['cert_' . $key . '_size']) : $field['size'],
                'color' => isset($_POST['cert_' . $key . '_color']) ? wp_unslash($_POST['cert_' . $key . '_color']) : $field['color'],
                'align' => isset($_POST['cert_' . $key . '_align']) ? wp_unslash($_POST['cert_' . $key . '_align']) : $field['align'],
            );
            $layout[$key] = self::normalize_field($posted, $field);
        }
        return $layout;
    }

    /**
     * Labels shown on the position editor.
     *
     * @return array
     */
    public static function field_labels() {
        return array(
            'name' => __('Name', 'exam-hall'),
            'subject' => __('Subject', 'exam-hall'),
            'grade' => __('Grade', 'exam-hall'),
            'date' => __('Date', 'exam-hall'),
        );
    }

    /**
     * Sample words for the settings preview.
     *
     * @return array
     */
    public static function sample_fields() {
        return array(
            'name' => 'Ada Okoye',
            'subject' => 'General knowledge',
            'grade' => 'Distinction (92%)',
            'date' => date_i18n(get_option('date_format')),
        );
    }

    /**
     * Words printed for a real sitting.
     *
     * @param object $attempt Submitted attempt.
     * @param object $exam    Exam.
     * @param object $student Student.
     * @return array
     */
    public static function fields_for_attempt($attempt, $exam, $student) {
        $grade = trim((string) $attempt->classification);
        $percent = eh_format_percentage($attempt->percentage);
        if ($grade !== '') {
            $grade .= ' (' . $percent . ')';
        } else {
            $grade = $percent;
        }
        $date = $attempt->submitted_at ? eh_format_gmt($attempt->submitted_at, get_option('date_format')) : date_i18n(get_option('date_format'));
        return array(
            'name' => $student ? $student->display_name : '',
            'subject' => $exam ? $exam->title : '',
            'grade' => $grade,
            'date' => $date,
        );
    }

    /**
     * Send a PNG and stop.
     *
     * @param string $path     Template file.
     * @param array  $fields   name, subject, grade, date.
     * @param array  $layout   Field positions.
     * @param string $filename Download name.
     * @param bool   $download Attachment when true.
     * @return void
     */
    public static function send($path, $fields, $layout, $filename, $download) {
        $image = self::compose($path, $fields, $layout);
        if (is_wp_error($image)) {
            wp_die(esc_html($image->get_error_message()));
        }
        nocache_headers();
        header('Content-Type: image/png');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . sanitize_file_name($filename) . '"');
        imagepng($image);
        imagedestroy($image);
        exit;
    }

    /**
     * Paint the four fields onto a copy of the template.
     *
     * @param string $path   PNG path.
     * @param array  $fields Text values.
     * @param array  $layout Positions.
     * @return resource|GdImage|WP_Error
     */
    public static function compose($path, $fields, $layout) {
        if (!self::can_render()) {
            return new WP_Error('eh_gd', __('This server cannot draw text onto PNG images.', 'exam-hall'));
        }
        if (!is_readable($path)) {
            return new WP_Error('eh_template', __('The certificate template file is missing.', 'exam-hall'));
        }
        $image = imagecreatefrompng($path);
        if (!$image) {
            return new WP_Error('eh_template', __('The certificate template could not be read.', 'exam-hall'));
        }
        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, true);
        imagesavealpha($image, true);
        $width = imagesx($image);
        $height = imagesy($image);
        foreach (self::default_layout() as $key => $fallback) {
            $field = isset($layout[$key]) ? self::normalize_field($layout[$key], $fallback) : $fallback;
            $text = isset($fields[$key]) ? trim((string) $fields[$key]) : '';
            if ($text === '') {
                continue;
            }
            $font = self::font_path($key === 'name' || $key === 'grade');
            $max = (int) floor($width * 0.86);
            $size = max(12, (int) round($width * ((float) $field['size'] / 100)));
            $lines = array($text);
            while ($size > 14) {
                $lines = self::wrap($text, $font, $size, $max);
                $too_wide = false;
                foreach ($lines as $line) {
                    if (self::line_width($font, $size, $line) > $max) {
                        $too_wide = true;
                        break;
                    }
                }
                if (!$too_wide && count($lines) <= 3) {
                    break;
                }
                $size -= 2;
            }
            $lines = self::wrap($text, $font, $size, $max);
            $color = self::allocate($image, $field['color']);
            $line_height = (int) round($size * 1.25);
            $block = count($lines) * $line_height;
            $center_y = ($field['y'] / 100) * $height;
            $top = $center_y - ($block / 2);
            $anchor = ($field['x'] / 100) * $width;
            foreach ($lines as $index => $line) {
                $text_width = self::line_width($font, $size, $line);
                if ($field['align'] === 'left') {
                    $x = (int) round($anchor);
                } elseif ($field['align'] === 'right') {
                    $x = (int) round($anchor - $text_width);
                } else {
                    $x = (int) round($anchor - ($text_width / 2));
                }
                $y = (int) round($top + ($index * $line_height));
                if (self::has_freetype()) {
                    imagettftext($image, $size, 0, $x, $y + $size, $color, $font, $line);
                } else {
                    self::draw_bitmap($image, $line, $size, $x, $y, $color);
                }
            }
        }
        return $image;
    }

    /**
     * Scale the built-in GD font when FreeType is unavailable.
     *
     * @param resource|GdImage $image Destination.
     * @param string           $text  Line.
     * @param int              $size  Target pixel height.
     * @param int              $x     Left position.
     * @param int              $y     Top position.
     * @param int              $color Colour index.
     */
    private static function draw_bitmap($image, $text, $size, $x, $y, $color) {
        $plain = $text;
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if (is_string($converted) && $converted !== '') {
                $plain = $converted;
            }
        }
        $plain = preg_replace('/[^\x20-\x7E]/', '', $plain);
        if ($plain === '') {
            return;
        }
        $font = 5;
        $src_w = imagefontwidth($font) * strlen($plain);
        $src_h = imagefontheight($font);
        $stamp = imagecreatetruecolor($src_w, $src_h);
        $blank = imagecolorallocate($stamp, 0, 0, 0);
        imagefilledrectangle($stamp, 0, 0, $src_w, $src_h, $blank);
        $ink = imagecolorallocate($stamp, 255, 255, 255);
        imagestring($stamp, $font, 0, 0, $plain, $ink);
        $scale = max(1, $size / $src_h);
        $dest_w = (int) round($src_w * $scale);
        $dest_h = (int) round($src_h * $scale);
        for ($dy = 0; $dy < $dest_h; $dy++) {
            $sy = min($src_h - 1, (int) floor($dy / $scale));
            for ($dx = 0; $dx < $dest_w; $dx++) {
                $sx = min($src_w - 1, (int) floor($dx / $scale));
                if (imagecolorat($stamp, $sx, $sy) === $ink) {
                    imagesetpixel($image, $x + $dx, $y + $dy, $color);
                }
            }
        }
        imagedestroy($stamp);
    }

    /**
     * Bold or regular Liberation Sans.
     *
     * @param bool $bold Bold face.
     * @return string
     */
    private static function font_path($bold) {
        return EH_PATH . 'assets/fonts/' . ($bold ? 'LiberationSans-Bold.ttf' : 'LiberationSans-Regular.ttf');
    }

    /**
     * Keep a posted field inside the editor bounds.
     *
     * @param array $posted   Raw field.
     * @param array $fallback Defaults.
     * @return array
     */
    private static function normalize_field($posted, $fallback) {
        $x = isset($posted['x']) ? (float) $posted['x'] : (float) $fallback['x'];
        $y = isset($posted['y']) ? (float) $posted['y'] : (float) $fallback['y'];
        $size = isset($posted['size']) ? (float) $posted['size'] : (float) $fallback['size'];
        $align = isset($posted['align']) ? (string) $posted['align'] : $fallback['align'];
        $color = isset($posted['color']) ? (string) $posted['color'] : $fallback['color'];
        if (!in_array($align, array('left', 'center', 'right'), true)) {
            $align = 'center';
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = $fallback['color'];
        }
        return array(
            'x' => max(0, min(100, $x)),
            'y' => max(0, min(100, $y)),
            'size' => max(1, min(12, $size)),
            'color' => $color,
            'align' => $align,
        );
    }

    /**
     * Split text so each line fits the template.
     *
     * @param string $text     Text.
     * @param string $font     Font path.
     * @param int    $size     Point size.
     * @param int    $maxWidth Maximum pixel width.
     * @return array
     */
    private static function wrap($text, $font, $size, $maxWidth) {
        $words = preg_split('/\s+/', trim($text));
        $lines = array();
        $current = '';
        foreach ($words as $word) {
            $try = $current === '' ? $word : $current . ' ' . $word;
            if ($current === '' || self::line_width($font, $size, $try) <= $maxWidth) {
                $current = $try;
                continue;
            }
            $lines[] = $current;
            $current = $word;
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        return $lines ? $lines : array('');
    }

    /**
     * Pixel width of one line.
     *
     * @param string $font Font path.
     * @param int    $size Point size.
     * @param string $text Line.
     * @return int
     */
    private static function line_width($font, $size, $text) {
        if (self::has_freetype()) {
            $box = imagettfbbox($size, 0, $font, $text);
            if (!is_array($box)) {
                return 0;
            }
            return (int) abs($box[2] - $box[0]);
        }
        $plain = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) : $text;
        if (!is_string($plain) || $plain === '') {
            $plain = $text;
        }
        $plain = preg_replace('/[^\x20-\x7E]/', '', $plain);
        $native = imagefontwidth(5) * max(1, strlen($plain));
        $scale = max(1, $size / imagefontheight(5));
        return (int) round($native * $scale);
    }

    /**
     * Allocate a hex colour.
     *
     * @param resource|GdImage $image Image.
     * @param string           $hex   #rrggbb.
     * @return int
     */
    private static function allocate($image, $hex) {
        $hex = ltrim($hex, '#');
        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));
        return imagecolorallocate($image, $red, $green, $blue);
    }
}
