<?php declare(strict_types=1);

use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Base3Ilias\PageComponent\AbstractPageComponentPluginGUI;

/**
 * @ilCtrl_isCalledBy ilBase3MermaidPageComponentPluginGUI: ilPCPluggedGUI
 */
class ilBase3MermaidPageComponentPluginGUI extends AbstractPageComponentPluginGUI
{
        protected function getPageComponentName(): string
        {
                return 'BASE3 Mermaid';
        }

        protected function getPageComponentDesc(): string
        {
                return 'BASE3 Mermaid Page Component';
        }

        protected function getDefaultProps(): array
        {
                return [
                        'mermaid_src' => '',
                        'mermaid' => '' // Fallback für alte gespeicherte Einträge
                ];
        }

        protected function setFormContent(ilPropertyFormGUI $form, array $props): void
        {
                $this->mainTemplate->addJavaScript('components/Base3/ClientStack/assetloader/assetloader.min.js');

                $value = (string) ($props['mermaid_src'] ?? $props['mermaid'] ?? '');

                $mermaid = new ilTextAreaInputGUI('Mermaid', 'mermaid_src');
                $mermaid->setValue($value);
                $mermaid->setRows(12);
                $form->addItem($mermaid);

                $preview = new ilCustomInputGUI('Vorschau');
                $preview->setHtml($this->getMermaidPreviewHtml());
                $form->addItem($preview);
        }

        protected function getPresentationHtml(array $a_properties, string $plugin_version): string
        {
                $this->mainTemplate->addJavaScript('components/Base3/ClientStack/assetloader/assetloader.min.js');

                $displays = $this->classmap->getInstances([
                        'interface' => IDisplay::class,
                        'name' => 'mermaiddisplay'
                ]);

                if (empty($displays)) {
                        return 'Display not found.';
                }

                $display = $displays[0];
                $display->setData([
                        'mermaid' => (string) ($a_properties['mermaid_src'] ?? $a_properties['mermaid'] ?? '')
                ]);

                return $display->getOutput();
        }

        protected function getMermaidPreviewHtml(): string
        {
                $preview_id = 'b3-mermaid-preview-' . md5(uniqid('', true));
                $mermaid_src = $this->getMermaidAssetUrl();

                $preview_id_js = json_encode($preview_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
                $mermaid_src_js = json_encode($mermaid_src, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

                return <<<HTML
<div id="{$preview_id}" style="min-height:120px; padding:1rem; border:1px solid #dcdcdc; background:#fff; overflow:auto;">
        <div style="color:#777;">Keine Vorschau verfügbar.</div>
</div>

<script>
(function() {
        const previewId = {$preview_id_js};
        const mermaidSrc = {$mermaid_src_js};
        let renderTimer = null;

        function getTextarea() {
                return document.querySelector('textarea[name="mermaid_src"], textarea[id*="mermaid_src"]');
        }

        function sleep(ms) {
                return new Promise(function(resolve) {
                        window.setTimeout(resolve, ms);
                });
        }

        function normalizeMermaid(candidate) {
                if (!candidate) {
                        return null;
                }

                if (
                        typeof candidate.initialize === 'function' ||
                        typeof candidate.run === 'function' ||
                        typeof candidate.render === 'function'
                ) {
                        return candidate;
                }

                if (candidate.default) {
                        return normalizeMermaid(candidate.default);
                }

                if (candidate.mermaid) {
                        return normalizeMermaid(candidate.mermaid);
                }

                return null;
        }

        async function ensureMermaid() {
                const existing = normalizeMermaid(window.mermaid);
                if (existing) {
                        return existing;
                }

                if (typeof AssetLoader === 'undefined') {
                        throw new Error('AssetLoader ist nicht verfügbar');
                }

                if (!window.__b3_mermaid_preview_load_started__) {
                        window.__b3_mermaid_preview_load_started__ = true;
                        AssetLoader.loadScript(mermaidSrc);
                }

                const started = Date.now();

                while ((Date.now() - started) < 5000) {
                        const loaded = normalizeMermaid(window.mermaid);
                        if (loaded) {
                                return loaded;
                        }
                        await sleep(50);
                }

                throw new Error('Mermaid wurde nicht geladen');
        }

        async function renderPreview() {
                const textarea = getTextarea();
                const host = document.getElementById(previewId);

                if (!textarea || !host) {
                        return;
                }

                const source = textarea.value || '';
                host.innerHTML = '';

                if (!source.trim()) {
                        host.innerHTML = '<div style="color:#777;">Keine Vorschau verfügbar.</div>';
                        return;
                }

                try {
                        const mermaid = await ensureMermaid();

                        if (!window.__b3_mermaid_initialized__) {
                                if (typeof mermaid.initialize === 'function') {
                                        mermaid.initialize({
                                                startOnLoad: false
                                        });
                                } else if (mermaid.mermaidAPI && typeof mermaid.mermaidAPI.initialize === 'function') {
                                        mermaid.mermaidAPI.initialize({
                                                startOnLoad: false
                                        });
                                }

                                window.__b3_mermaid_initialized__ = true;
                        }

                        const renderId = 'b3-mermaid-render-' + Date.now();

                        if (typeof mermaid.render === 'function') {
                                const result = await mermaid.render(renderId, source);
                                host.innerHTML = result.svg || result;

                                if (result.bindFunctions) {
                                        result.bindFunctions(host);
                                }
                        } else if (typeof mermaid.run === 'function') {
                                const node = document.createElement('div');
                                node.className = 'mermaid';
                                node.textContent = source;
                                host.appendChild(node);

                                await mermaid.run({
                                        querySelector: '#' + previewId + ' .mermaid'
                                });
                        } else if (mermaid.mermaidAPI && typeof mermaid.mermaidAPI.render === 'function') {
                                mermaid.mermaidAPI.render(renderId, source, function(svgCode) {
                                        host.innerHTML = svgCode;
                                });
                        } else {
                                throw new Error('Keine passende Mermaid-Render-API gefunden');
                        }
                } catch (e) {
                        console.error('Mermaid-Preview fehlgeschlagen:', e);
                        host.innerHTML = '<pre style="white-space:pre-wrap;color:#b00020;">Mermaid-Fehler: '
                                + (e && e.message ? e.message : e)
                                + '</pre>';
                }
        }

        function scheduleRender() {
                window.clearTimeout(renderTimer);
                renderTimer = window.setTimeout(renderPreview, 250);
        }

        function initPreview() {
                const textarea = getTextarea();
                if (!textarea) {
                        return;
                }

                if (textarea.dataset.b3MermaidPreviewBound !== '1') {
                        textarea.dataset.b3MermaidPreviewBound = '1';
                        textarea.addEventListener('input', scheduleRender);
                        textarea.addEventListener('change', scheduleRender);
                }

                renderPreview();
        }

        if (document.readyState !== 'loading') {
                initPreview();
        } else {
                document.addEventListener('DOMContentLoaded', initPreview, { once: true });
        }

        window.addEventListener('load', initPreview, { once: true });
        window.addEventListener('mermaid:init', initPreview);
})();
</script>
HTML;
        }

        protected function getMermaidAssetUrl(): string
        {
/*
		$resolvers = $this->classmap->getInstances([
                        'interface' => IAssetResolver::class
                ]);

                if (!empty($resolvers) && method_exists($resolvers[0], 'resolve')) {
                        return (string) $resolvers[0]->resolve('plugin/Mermaid/assets/mermaid/mermaid.min.js');
                }
 */
                return 'components/Base3/Mermaid/mermaid/mermaid.min.js';
        }
}
