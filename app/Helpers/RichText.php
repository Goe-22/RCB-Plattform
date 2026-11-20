<?php
declare(strict_types=1);

/**
 * Bereinigt formatierten Text aus dem Seiten-Editor.
 * Erlaubt nur einfache Formatierungen, Links und Bilder; alles andere wird entfernt.
 */
final class RichText
{
    private const TAGS = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
        'a' => ['href'], 'img' => ['src', 'alt'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'td' => ['colspan'], 'th' => ['colspan'],
    ];
    private const RENAME = ['b' => 'strong', 'i' => 'em', 'h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4', 'div' => 'p'];

    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        if (!class_exists('DOMDocument')) {
            return strip_tags($html, '<p><br><strong><em><u><h2><h3><h4><ul><ol><li><blockquote>');
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="rcb-root">' . $html . '</div></body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $doc->getElementById('rcb-root');
        if (!$root) {
            return '';
        }
        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= self::node($child);
        }
        // Leere Absätze am Ende entfernen, mehrfache Leerzeilen begrenzen.
        $out = preg_replace('#(<p>(\s|<br>)*</p>)+$#', '', $out) ?? $out;
        return trim($out);
    }

    private static function node(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return htmlspecialchars($node->wholeText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (!$node instanceof DOMElement) {
            return '';
        }
        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'template'], true)) {
            return '';
        }
        $tag = self::RENAME[$tag] ?? $tag;
        $inner = '';
        foreach (iterator_to_array($node->childNodes) as $child) {
            $inner .= self::node($child);
        }
        if (!array_key_exists($tag, self::TAGS)) {
            return $inner;
        }
        if ($tag === 'br') {
            return '<br>';
        }
        $attrs = '';
        foreach (self::TAGS[$tag] as $name) {
            if (!$node->hasAttribute($name)) {
                continue;
            }
            $value = trim($node->getAttribute($name));
            if (in_array($name, ['href', 'src'], true) && !self::safeUrl($value, $name === 'src')) {
                continue;
            }
            if ($name === 'colspan') {
                $value = (string)max(1, min(20, (int)$value));
            }
            $attrs .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        if ($tag === 'img') {
            return str_contains($attrs, ' src=') ? '<img' . $attrs . ' loading="lazy">' : '';
        }
        if ($tag === 'a') {
            if (!str_contains($attrs, ' href=')) {
                return $inner;
            }
            if (preg_match('#href="https?://#', $attrs)) {
                $attrs .= ' target="_blank" rel="noopener noreferrer"';
            }
        }
        return '<' . $tag . $attrs . '>' . $inner . '</' . $tag . '>';
    }

    private static function safeUrl(string $url, bool $image): bool
    {
        if ($url === '') {
            return false;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return $image ? in_array($scheme, ['https', 'http'], true) : in_array($scheme, ['https', 'http', 'mailto', 'tel'], true);
    }

    /** Wandelt einfachen Text (mit Zeilenumbrüchen) in sicheres HTML um. */
    public static function fromPlain(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $paragraphs = preg_split("/\R{2,}/", $text) ?: [$text];
        return implode('', array_map(
            static fn(string $p): string => '<p>' . nl2br(htmlspecialchars(trim($p), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . '</p>',
            $paragraphs
        ));
    }
}
