<?php declare(strict_types=1);

use Base3\Api\IDisplay;
use Base3\Base3Ilias\PageComponent\AbstractPageComponentPluginGUI;

/**
 * @ilCtrl_isCalledBy ilBase3MermaidPageComponentPluginGUI: ilPCPluggedGUI
 */
class ilBase3MermaidPageComponentPluginGUI extends AbstractPageComponentPluginGUI {

	private const MERMAID_STORAGE_PREFIX = 'base64:';

	protected function getPageComponentName(): string {
		return 'BASE3 Mermaid';
	}

	protected function getPageComponentDesc(): string {
		return 'BASE3 Mermaid Page Component';
	}

	protected function getDefaultProps(): array {
		return [
			'mermaid_src' => ''
		];
	}

	protected function setFormContent(ilPropertyFormGUI $form, array $props): void {
		$this->mainTemplate->addJavaScript('components/Base3/ClientStack/assetloader/assetloader.min.js');

		$value = $this->decodeMermaidProperty($this->getMermaidProperty($props));

		$mermaid = new ilTextAreaInputGUI('Mermaid Source', 'mermaid_src');
		$mermaid->setValue($value);
		$mermaid->setRows(12);
		$form->addItem($mermaid);

		$editor = new ilCustomInputGUI('Diagram');
		$editor->setHtml($this->getMermaidEditorHtml());
		$form->addItem($editor);
	}

	protected function getPresentationHtml(array $a_properties, string $plugin_version): string {
		$this->mainTemplate->addJavaScript('components/Base3/ClientStack/assetloader/assetloader.min.js');

		$displays = $this->classmap->getInstances([
			'interface' => IDisplay::class,
			'name' => 'mermaiddisplay'
		]);

		if (empty($displays)) {
			return 'Display not found.';
		}

		$source = $this->decodeMermaidProperty($this->getMermaidProperty($a_properties));

		$display = $displays[0];
		$display->setData([
			'mermaid' => $source
		]);

		return $display->getOutput();
	}

	protected function beforeCreateElement(ilPropertyFormGUI $form, array &$props): bool {
		$props['mermaid_src'] = $this->encodeMermaidProperty(
			(string) ($props['mermaid_src'] ?? '')
		);

		return true;
	}

	protected function beforeUpdateElement(ilPropertyFormGUI $form, array &$props): bool {
		$props['mermaid_src'] = $this->encodeMermaidProperty(
			(string) ($props['mermaid_src'] ?? '')
		);

		return true;
	}

	private function getMermaidProperty(array $properties): string {
		$source = (string) ($properties['mermaid_src'] ?? '');

		if ($source === '') {
			$source = (string) ($properties['mermaid'] ?? '');
		}

		return $source;
	}

	private function encodeMermaidProperty(string $source): string {
		$source = str_replace(["\r\n", "\r"], "\n", $source);

		return self::MERMAID_STORAGE_PREFIX . base64_encode($source);
	}

	private function decodeMermaidProperty(string $source): string {
		$source = html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		if (str_starts_with($source, self::MERMAID_STORAGE_PREFIX)) {
			$decoded = base64_decode(substr($source, strlen(self::MERMAID_STORAGE_PREFIX)), true);

			if ($decoded === false) {
				throw new RuntimeException('Invalid encoded Mermaid source.');
			}

			$source = $decoded;
		} else {
			$source = str_ireplace(
				['&#13;', '&#x0d;', '&#xd;', '&#10;', '&#x0a;', '&#xa;'],
				["\r", "\r", "\r", "\n", "\n", "\n"],
				$source
			);
		}

		return str_replace(["\r\n", "\r"], "\n", $source);
	}

	protected function getMermaidEditorHtml(): string {
		$prompt_id = 'b3-mermaid-prompt-' . md5(uniqid('', true));
		$button_id = 'b3-mermaid-generate-' . md5(uniqid('', true));
		$status_id = 'b3-mermaid-status-' . md5(uniqid('', true));
		$preview_id = 'b3-mermaid-preview-' . md5(uniqid('', true));
		$code_id = 'b3-mermaid-code-' . md5(uniqid('', true));
		$details_id = 'b3-mermaid-details-' . md5(uniqid('', true));
		$conversation_channel_id = 'mermaid-page-component:' . md5(uniqid('', true));
		$mermaid_src = $this->getMermaidAssetUrl();
		$ajax_url = $this->getMermaidGenerateUrl();

		$prompt_id_js = json_encode($prompt_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		$button_id_js = json_encode($button_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		$status_id_js = json_encode($status_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		$preview_id_js = json_encode($preview_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		$code_id_js = json_encode($code_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		$details_id_js = json_encode($details_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		$conversation_channel_id_js = json_encode($conversation_channel_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		$mermaid_src_js = json_encode($mermaid_src, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		$ajax_url_js = json_encode($ajax_url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

		return <<<HTML
<div style="display:flex; flex-direction:column; gap:1rem;">
	<div style="display:flex; flex-direction:column; gap:0.5rem;">
		<label for="{$prompt_id}" style="font-weight:600;">Prompt</label>
		<textarea
			id="{$prompt_id}"
			rows="4"
			placeholder="Describe the Mermaid diagram to generate"
			style="width:100%; box-sizing:border-box; resize:vertical;"
		></textarea>
		<div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
			<button type="button" class="btn btn-default" id="{$button_id}">Generate Mermaid</button>
			<div id="{$status_id}" style="color:#666;"></div>
		</div>
	</div>

	<div style="display:flex; flex-direction:column; gap:0.5rem;">
		<div style="font-weight:600;">Preview</div>
		<div id="{$preview_id}" style="min-height:180px; padding:1rem; border:1px solid #dcdcdc; background:#fff; overflow:auto;">
			<div style="color:#777;">No preview available.</div>
		</div>
	</div>

	<details id="{$details_id}">
		<summary style="cursor:pointer; font-weight:600;">Advanced: Mermaid code</summary>
		<div style="margin-top:0.75rem;">
			<textarea
				id="{$code_id}"
				rows="14"
				spellcheck="false"
				style="width:100%; box-sizing:border-box; font-family:monospace; resize:vertical;"
			></textarea>
		</div>
	</details>
</div>

<script>
(function() {
	const promptId = {$prompt_id_js};
	const buttonId = {$button_id_js};
	const statusId = {$status_id_js};
	const previewId = {$preview_id_js};
	const codeId = {$code_id_js};
	const detailsId = {$details_id_js};
	const conversationChannelId = {$conversation_channel_id_js};
	const mermaidSrc = {$mermaid_src_js};
	const ajaxUrl = {$ajax_url_js};

	let renderTimer = null;

	function getSourceTextarea() {
		return document.querySelector('textarea[name="mermaid_src"], textarea[id*="mermaid_src"]');
	}

	function getPromptTextarea() {
		return document.getElementById(promptId);
	}

	function getButton() {
		return document.getElementById(buttonId);
	}

	function getStatus() {
		return document.getElementById(statusId);
	}

	function getPreviewHost() {
		return document.getElementById(previewId);
	}

	function getCodeTextarea() {
		return document.getElementById(codeId);
	}

	function getDetails() {
		return document.getElementById(detailsId);
	}

	function setStatus(message, isError) {
		const status = getStatus();
		if (!status) {
			return;
		}

		status.textContent = message || '';
		status.style.color = isError ? '#b00020' : '#666';
	}

	function findSourceContainer(textarea) {
		if (!textarea) {
			return null;
		}

		const selectors = [
			'.form-group',
			'.control-group',
			'.ilFormItem',
			'.c-form__item',
			'tr'
		];

		for (let i = 0; i < selectors.length; i++) {
			const container = textarea.closest(selectors[i]);
			if (container) {
				return container;
			}
		}

		return textarea;
	}

	function hideSourceField() {
		const textarea = getSourceTextarea();
		if (!textarea) {
			return;
		}

		const container = findSourceContainer(textarea);
		if (container) {
			container.style.display = 'none';
		}
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
			throw new Error('AssetLoader is not available');
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

		throw new Error('Mermaid could not be loaded');
	}

	function getCurrentMermaid() {
		const codeTextarea = getCodeTextarea();
		if (codeTextarea) {
			return codeTextarea.value || '';
		}

		const sourceTextarea = getSourceTextarea();
		if (sourceTextarea) {
			return sourceTextarea.value || '';
		}

		return '';
	}

	function setCurrentMermaid(value) {
		const normalized = value || '';
		const sourceTextarea = getSourceTextarea();
		const codeTextarea = getCodeTextarea();

		if (sourceTextarea) {
			sourceTextarea.value = normalized;
		}

		if (codeTextarea && codeTextarea.value !== normalized) {
			codeTextarea.value = normalized;
		}
	}

	async function renderPreview() {
		const host = getPreviewHost();
		if (!host) {
			return;
		}

		const source = getCurrentMermaid();
		host.innerHTML = '';

		if (!source.trim()) {
			host.innerHTML = '<div style="color:#777;">No preview available.</div>';
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
				throw new Error('No compatible Mermaid render API found');
			}
		} catch (e) {
			console.error('Mermaid preview failed:', e);
			host.innerHTML = '<pre style="white-space:pre-wrap;color:#b00020;">Mermaid error: '
				+ (e && e.message ? e.message : e)
				+ '</pre>';
		}
	}

	function scheduleRender() {
		window.clearTimeout(renderTimer);
		renderTimer = window.setTimeout(renderPreview, 250);
	}

	function bindCodeEditor() {
		const codeTextarea = getCodeTextarea();
		if (!codeTextarea) {
			return;
		}

		if (codeTextarea.dataset.b3MermaidCodeBound === '1') {
			return;
		}

		codeTextarea.dataset.b3MermaidCodeBound = '1';

		function syncCodeToSource() {
			setCurrentMermaid(codeTextarea.value || '');
			scheduleRender();
		}

		codeTextarea.addEventListener('input', syncCodeToSource);
		codeTextarea.addEventListener('change', syncCodeToSource);
	}

	async function generateMermaid() {
		const promptTextarea = getPromptTextarea();
		const button = getButton();

		if (!promptTextarea || !button) {
			return;
		}

		const prompt = (promptTextarea.value || '').trim();
		const mermaid = getCurrentMermaid();

		if (!prompt) {
			setStatus('Please enter a prompt first.', true);
			promptTextarea.focus();
			return;
		}

		button.disabled = true;
		setStatus('Generating Mermaid ...', false);

		try {
			const response = await fetch(ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With': 'XMLHttpRequest'
				},
				body: new URLSearchParams({
					prompt: prompt,
					mermaid: mermaid,
					conversation_channel_id: conversationChannelId
				}).toString()
			});

			const responseText = await response.text();

			if (!response.ok) {
				throw new Error(responseText.trim() || ('HTTP ' + response.status));
			}

			const generatedMermaid = responseText;
			setCurrentMermaid(generatedMermaid);
			scheduleRender();

			const details = getDetails();
			if (details) {
				details.open = false;
			}

			setStatus('Mermaid updated.', false);
		} catch (e) {
			console.error('Mermaid generation failed:', e);
			setStatus('Generation failed: ' + (e && e.message ? e.message : e), true);
		} finally {
			button.disabled = false;
		}
	}

	function bindGenerator() {
		const button = getButton();
		const promptTextarea = getPromptTextarea();

		if (button && button.dataset.b3MermaidGeneratorBound !== '1') {
			button.dataset.b3MermaidGeneratorBound = '1';
			button.addEventListener('click', generateMermaid);
		}

		if (promptTextarea && promptTextarea.dataset.b3MermaidPromptBound !== '1') {
			promptTextarea.dataset.b3MermaidPromptBound = '1';
			promptTextarea.addEventListener('keydown', function(event) {
				if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
					event.preventDefault();
					generateMermaid();
				}
			});
		}
	}

	function initEditor() {
		const sourceTextarea = getSourceTextarea();
		const codeTextarea = getCodeTextarea();

		if (!sourceTextarea || !codeTextarea) {
			return;
		}

		hideSourceField();
		codeTextarea.value = sourceTextarea.value || '';
		bindCodeEditor();
		bindGenerator();
		renderPreview();
	}

	if (document.readyState !== 'loading') {
		initEditor();
	} else {
		document.addEventListener('DOMContentLoaded', initEditor, { once: true });
	}

	window.addEventListener('load', initEditor, { once: true });
	window.addEventListener('mermaid:init', initEditor);
})();
</script>
HTML;
	}

	protected function getMermaidGenerateUrl(): string {
		$ctrl = $GLOBALS['DIC']->ctrl();

		return $ctrl->getLinkTargetByClass(
			['ilUIPluginRouterGUI', 'ilBase3MermaidPageComponentAjaxGUI'],
			'generate',
			'',
			true
		);
	}

	protected function getMermaidAssetUrl(): string {
		return 'components/Base3/ClientStack/mermaid/mermaid.min.js';
	}
}
