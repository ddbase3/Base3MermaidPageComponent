<?php declare(strict_types=1);

use AssistantFoundation\Api\IAgentExecutionService;
use AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\Core\ServiceLocator;
use Base3\Settings\Api\ISettingsStore;

/**
 * @ilCtrl_IsCalledBy ilBase3MermaidPageComponentAjaxGUI: ilUIPluginRouterGUI
 */
class ilBase3MermaidPageComponentAjaxGUI {

	protected const AGENT_SETTINGS_GROUP = 'agent';
	protected const AGENT_ID = 'mermaid-agent';
	protected const DEFAULT_ASSISTANT_NODE_ID = 'assistant';

	protected ilCtrl $ctrl;

	public function __construct() {
		$this->ctrl = $GLOBALS['DIC']->ctrl();
	}

	public function executeCommand(): void {
		$cmd = $this->ctrl->getCmd('render');
		if (!method_exists($this, $cmd)) {
			$cmd = 'render';
		}

		$this->$cmd();
	}

	protected function render(): void {
		$this->sendText(implode("\n", [
			'flowchart TD',
			"\tA[Mermaid Generator] --> B[Ready]",
			"\tB --> C[Use the prompt field]",
			"\tC --> D[Generate diagram]"
		]));
	}

	protected function generate(): void {
		try {
			$prompt = trim((string)($_POST['prompt'] ?? ''));
			$mermaid = trim((string)($_POST['mermaid'] ?? ''));
			$conversation_channel_id = $this->normalizeConversationChannelId(
				(string)($_POST['conversation_channel_id'] ?? '')
			);

			if ($prompt === '') {
				$this->sendError('Missing prompt.');
			}

			$settings = $this->getAgentSettings();
			$request = new AgentExecutionRequest(
				$settings,
				$this->buildAgentInputs($settings, $prompt, $mermaid),
				$this->buildAgentContextVars($settings, $conversation_channel_id, $prompt, $mermaid)
			);
			$result = $this->getAgentExecutionService()->execute($request);
			$output = $result->getOutput();
			$agent_result = $result->getAgentResult();

			if ($output === [] && $agent_result !== null) {
				$output = $agent_result->getOutput();
			}

			$assistant_node_id = $this->getAssistantNodeId($settings);
			$response = $this->extractAssistantResponse($output, $assistant_node_id);

			if ($response === '' && $agent_result?->hasFailure()) {
				$metadata = $agent_result->getMetadata();
				$error = trim((string)($metadata['error'] ?? $metadata['message'] ?? 'Agent runtime reported a failed execution.'));
				throw new RuntimeException($error);
			}

			if ($response === '') {
				$error = $this->extractFlowError($output, $assistant_node_id);
				if ($error !== '') {
					throw new RuntimeException($error);
				}
			}

			$response = $this->normalizeMermaidResponse($response);

			if ($response === '') {
				$this->sendError('Configured Mermaid agent finished without Mermaid output.');
			}

			$this->sendText($response);
		} catch (Throwable $e) {
			$this->sendError($e->getMessage());
		}
	}

	protected function getAgentExecutionService(): IAgentExecutionService {
		$service = ServiceLocator::getInstance()->get(IAgentExecutionService::class);

		if (!$service instanceof IAgentExecutionService) {
			throw new RuntimeException('BASE3 agent execution service is not available.');
		}

		return $service;
	}

	protected function getSettingsStore(): ISettingsStore {
		$store = ServiceLocator::getInstance()->get(ISettingsStore::class);

		if (!$store instanceof ISettingsStore) {
			throw new RuntimeException('BASE3 settings store is not available.');
		}

		return $store;
	}

	protected function getAgentSettings(): array {
		$settings = $this->getSettingsStore()->get(
			self::AGENT_SETTINGS_GROUP,
			self::AGENT_ID,
			[]
		);

		if ($settings === []) {
			throw new RuntimeException(
				'Configured Mermaid agent not found: '
				. self::AGENT_SETTINGS_GROUP
				. '/'
				. self::AGENT_ID
			);
		}

		if (array_key_exists('enabled', $settings) && !$this->toBool($settings['enabled'])) {
			throw new RuntimeException('Configured Mermaid agent is disabled: ' . self::AGENT_ID);
		}

		return $settings;
	}

	protected function buildAgentInputs(array $settings, string $prompt, string $current_mermaid): array {
		return [
			'system' => $this->normalizeTextBlock((string)($settings['system_prompt'] ?? '')),
			'prompt' => $this->buildUserPrompt($prompt, $current_mermaid),
			'mode' => 'mermaid_generator'
		];
	}

	protected function buildUserPrompt(string $prompt, string $current_mermaid): string {
		$parts = [
			'Create or update the Mermaid diagram for this request:',
			$prompt
		];

		if ($current_mermaid === '') {
			$parts[] = 'There is no existing Mermaid diagram.';
		} else {
			$parts[] = 'Current Mermaid diagram:';
			$parts[] = '---CURRENT_MERMAID_START---';
			$parts[] = $current_mermaid;
			$parts[] = '---CURRENT_MERMAID_END---';
		}

		$parts[] = 'Return the complete updated Mermaid source.';

		return implode("\n", $parts);
	}

	protected function buildAgentContextVars(
		array $settings,
		string $conversation_channel_id,
		string $prompt,
		string $current_mermaid
	): array {
		return [
			'conversation_channel_id' => $conversation_channel_id,
			'agent_id' => self::AGENT_ID,
			'agent_label' => trim((string)($settings['label'] ?? '')),
			'agent_config' => $settings,
			'agent_run_mode' => 'mermaid_page_component',
			'mermaid_generator_prompt' => $prompt,
			'mermaid_generator_current_mermaid' => $current_mermaid
		];
	}

	protected function normalizeConversationChannelId(string $channel_id): string {
		$channel_id = trim($channel_id);

		if ($channel_id === '') {
			throw new RuntimeException('Missing conversation_channel_id.');
		}

		if (strlen($channel_id) > 200 || preg_match('/^[a-zA-Z0-9._:-]+$/', $channel_id) !== 1) {
			throw new RuntimeException('Invalid conversation_channel_id.');
		}

		return $channel_id;
	}

	protected function getAssistantNodeId(array $settings): string {
		$node_id = trim((string)($settings['agent_components_assistant_node'] ?? self::DEFAULT_ASSISTANT_NODE_ID));

		return $node_id !== '' ? $node_id : self::DEFAULT_ASSISTANT_NODE_ID;
	}

	protected function extractAssistantResponse(array $output, string $assistant_node_id): string {
		$message = $this->extractAssistantMessage($output, $assistant_node_id);
		if ($message === null) {
			return '';
		}

		return $this->normalizeMessageContent($message['content'] ?? '');
	}

	protected function extractAssistantMessage(array $output, string $assistant_node_id): ?array {
		if (isset($output[$assistant_node_id]['message']) && is_array($output[$assistant_node_id]['message'])) {
			return $output[$assistant_node_id]['message'];
		}

		if (isset($output['assistant']['message']) && is_array($output['assistant']['message'])) {
			return $output['assistant']['message'];
		}

		foreach ($output as $node_output) {
			if (is_array($node_output) && isset($node_output['message']) && is_array($node_output['message'])) {
				return $node_output['message'];
			}
		}

		return null;
	}

	protected function extractFlowError(array $output, string $assistant_node_id): string {
		if (isset($output[$assistant_node_id]['error']) && is_scalar($output[$assistant_node_id]['error'])) {
			return trim((string)$output[$assistant_node_id]['error']);
		}

		if (isset($output['assistant']['error']) && is_scalar($output['assistant']['error'])) {
			return trim((string)$output['assistant']['error']);
		}

		foreach ($output as $node_output) {
			if (is_array($node_output) && isset($node_output['error']) && is_scalar($node_output['error'])) {
				return trim((string)$node_output['error']);
			}
		}

		return '';
	}

	protected function normalizeMessageContent(mixed $content): string {
		if ($content === null) {
			return '';
		}

		if (is_string($content)) {
			return $content;
		}

		if (is_bool($content)) {
			return $content ? 'true' : 'false';
		}

		if (is_int($content) || is_float($content)) {
			return (string)$content;
		}

		$json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return is_string($json) && $json !== 'null' ? $json : '';
	}

	protected function normalizeTextBlock(string $value): string {
		$value = str_replace(["\r\n", "\r"], "\n", $value);

		return trim($value);
	}

	protected function normalizeMermaidResponse(string $response): string {
		$response = trim($response);

		if ($response === '') {
			return '';
		}

		$response = preg_replace('/^```mermaid\s*/i', '', $response) ?? $response;
		$response = preg_replace('/^```[\t ]*\r?\n?/i', '', $response) ?? $response;
		$response = preg_replace('/\r?\n?```$/i', '', $response) ?? $response;

		return trim($response);
	}

	protected function toBool(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value) || is_float($value)) {
			return $value != 0;
		}

		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on', 'enabled'], true);
	}

	protected function sendText(string $content): void {
		header('Content-Type: text/plain; charset=UTF-8');
		echo $content;
		exit;
	}

	protected function sendError(string $message): void {
		http_response_code(500);
		header('Content-Type: text/plain; charset=UTF-8');
		echo $message;
		exit;
	}
}
