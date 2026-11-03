<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Texto con formato de una campaña (el editor del panel) limpio para un correo, con
 * lista blanca: párrafos, saltos de línea, negrita, cursiva y enlaces https. Todo lo
 * demás se quita: script, style, iframe, formularios e imágenes con su contenido; las
 * demás etiquetas se desarman dejando el texto. Ningún atributo sobrevive salvo el href
 * de un enlace https (sin usuario ni contraseña). Los <div> del editor pasan a <p>.
 *
 * limpiar() se usa al guardar y otra vez al armar el correo; conEstilos() pone los
 * estilos en línea de la plantilla (Gmail quita los <style>).
 */
class HtmlDeCorreo
{
    private const PERMITIDAS = ['p', 'br', 'strong', 'b', 'em', 'i', 'a'];

    /** Se borran con todo lo que tengan dentro. */
    private const PELIGROSAS = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'img', 'svg', 'math', 'video', 'audio', 'link', 'meta', 'head', 'title', 'template', 'noscript', 'frame', 'frameset', 'base'];

    public static function limpiar(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $anterior = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $body = $doc->getElementsByTagName('body')->item(0);
        if (! $body) {
            return '';
        }

        $salida = '';
        foreach (iterator_to_array($body->childNodes) as $hijo) {
            $salida .= self::nodo($hijo);
        }

        // Párrafos vacíos fuera; el texto suelto queda envuelto en <p>.
        $salida = (string) preg_replace('#<p>(\s|&nbsp;|<br>)*</p>#u', '', $salida);
        $salida = trim($salida);
        if ($salida !== '' && ! str_starts_with($salida, '<p>')) {
            $salida = '<p>'.$salida.'</p>';
        }

        return $salida;
    }

    /** Estilos en línea para la plantilla oscura del correo (contraste AA). */
    public static function conEstilos(string $limpio): string
    {
        return strtr($limpio, [
            '<p>' => '<p style="margin:0 0 16px 0; font-size:16px; line-height:25px; color:#C9C9C9;">',
            '<strong>' => '<strong style="color:#FFFFFF; font-weight:700;">',
            '<a href=' => '<a style="color:#FF7A85; text-decoration:underline;" target="_blank" rel="noopener" href=',
        ]);
    }

    private static function nodo(DOMNode $nodo): string
    {
        if ($nodo->nodeType === XML_TEXT_NODE || $nodo->nodeType === XML_CDATA_SECTION_NODE) {
            return htmlspecialchars((string) $nodo->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
        }

        if (! $nodo instanceof DOMElement) {
            return ''; // comentarios, instrucciones de proceso
        }

        $etiqueta = strtolower($nodo->tagName);
        if (in_array($etiqueta, self::PELIGROSAS, true)) {
            return '';
        }

        $interior = '';
        foreach (iterator_to_array($nodo->childNodes) as $hijo) {
            $interior .= self::nodo($hijo);
        }

        $etiqueta = match ($etiqueta) {
            'b' => 'strong',
            'i' => 'em',
            'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'li' => 'p',
            default => $etiqueta,
        };

        if ($etiqueta === 'br') {
            return '<br>';
        }

        if ($etiqueta === 'a') {
            $href = self::hrefSeguro($nodo->getAttribute('href'));

            return $href !== null && trim($interior) !== ''
                ? '<a href="'.htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8').'">'.$interior.'</a>'
                : $interior;
        }

        if (! in_array($etiqueta, self::PERMITIDAS, true)) {
            return $interior; // ul, span, table...: se desarman y queda el texto
        }

        // Un <p> dentro de otro (por ejemplo un <li> en un <div>) se aplana.
        if ($etiqueta === 'p') {
            $interior = (string) preg_replace('#</?p>#', ' ', $interior);
        }

        return "<{$etiqueta}>{$interior}</{$etiqueta}>";
    }

    /** Solo https, con host, sin usuario/contraseña ni caracteres de control. */
    public static function hrefSeguro(?string $href): ?string
    {
        $href = trim((string) $href);
        if ($href === '' || preg_match('/[\x00-\x20\x7F]/', $href)) {
            return null;
        }

        $partes = parse_url($href);
        if ($partes === false || strtolower($partes['scheme'] ?? '') !== 'https' || empty($partes['host'])
            || isset($partes['user']) || isset($partes['pass'])) {
            return null;
        }

        return $href;
    }
}
