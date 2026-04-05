<?php declare(strict_types=1);

use AssistantFoundation\Api\IAiTaskService;

/**
 * @ilCtrl_IsCalledBy ilBase3MermaidPageComponentAjaxGUI: ilUIPluginRouterGUI
 */
class ilBase3MermaidPageComponentAjaxGUI {

	protected ilCtrl $ctrl;
	protected IAiTaskService $aiTaskService;

	public function __construct() {
		$this->ctrl = $GLOBALS['DIC']->ctrl();
		$this->aiTaskService = $GLOBALS['DIC'][IAiTaskService::class];
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
			'	A[Mermaid Generator] --> B[Ready]',
			'	B --> C[Use the prompt field]',
			'	C --> D[Generate diagram]'
		]));
	}

	protected function generate(): void {
		try {
			$prompt = trim((string)($_POST['prompt'] ?? ''));
			$mermaid = trim((string)($_POST['mermaid'] ?? ''));

			if ($prompt === '') {
				$this->sendError('Missing prompt.');
			}

			$system_prompt = $this->buildSystemPrompt($mermaid);
			$user_prompt = $this->buildUserPrompt($prompt);
			$agent_flow = $this->getAgentFlow();

			$response = $this->aiTaskService->run($system_prompt, $user_prompt, $agent_flow);
			$response = $this->normalizeMermaidResponse($response);

			if ($response === '') {
				$this->sendError('Empty Mermaid response.');
			}

			$this->sendText($response);
		} catch (Throwable $e) {
			$this->sendError($e->getMessage());
		}
	}

	protected function buildSystemPrompt(string $current_mermaid): string {
		$instructions = [
			'You are a Mermaid diagram generator.',
			'Your task is to create or update a Mermaid diagram.',
			'Always return only Mermaid code.',
			'Do not wrap the answer in markdown code fences.',
			'Do not add explanations, prose, headings, or comments outside the Mermaid code.',
			'Return a complete Mermaid diagram, never a fragment.',
			'Use the Mermaid syntax helper tool whenever you need a supported type list, syntax guide, existing type detection, or a starter template.',
			'When the user explicitly requests a supported Mermaid diagram type, follow that type exactly.',
			'When the existing Mermaid code is provided, preserve its current diagram type unless the user explicitly asks for a different type.',
			'Do not invent Mermaid syntax from memory when the helper tool can provide the type guide or template.',
			'Prefer clear labels and a simple readable structure.',
			'Output must be directly renderable by Mermaid.',
			'If node labels contain parentheses, colons, brackets, or other punctuation, wrap the label in double quotes inside the node.',
			'Example: A["Label with (parentheses)"]'
		];

		if ($current_mermaid === '') {
			$instructions[] = 'There is currently no existing Mermaid diagram.';
		} else {
			$instructions[] = 'Current Mermaid diagram:';
			$instructions[] = '---CURRENT_MERMAID_START---';
			$instructions[] = $current_mermaid;
			$instructions[] = '---CURRENT_MERMAID_END---';
		}

		return implode("\n", $instructions);
	}

	protected function buildUserPrompt(string $prompt): string {
		return implode("\n", [
			'Update or create the Mermaid diagram according to this request:',
			$prompt
		]);
	}

	protected function getAgentFlow(): array {
		return [
			'nodes' => [
				[
					'id' => 'assistant',
					'type' => 'aiassistantnode',
					'docks' => [
						'chatmodel' => ['llm'],
						'memory' => ['timememory', 'sessionmemory'],
						'tools' => ['mermaidsyntax'],
						'logger' => ['log']
					]
				]
			],
			'resources' => [
				[
					'id' => 'llm',
					'type' => 'openaichatmodelagentresource',
					'config' => [
						'apikey' => ['mode' => 'config', 'section' => 'openai', 'key' => 'apikey'],
						'model' => ['mode' => 'fixed', 'value' => 'gpt-4o-mini'],
						'temperature' => ['mode' => 'fixed', 'value' => 0.3]
					]
				],
				[
					'id' => 'sessionmemory',
					'type' => 'sessionmemoryagentresource',
					'docks' => [
						'logger' => ['log']
					]
				],
				[
					'id' => 'timememory',
					'type' => 'timememoryagentresource'
				],
				[
					'id' => 'mermaidsyntax',
					'type' => 'mermaidsyntaxagenttool',
					'docks' => [
						'logger' => ['log']
					]
				],
				[
					'id' => 'log',
					'type' => 'loggerresource',
					'config' => [
						'scope' => ['mode' => 'fixed', 'value' => 'mermaid-generator']
					]
				]
			],
			'connections' => [
				[
					'from' => '__input__',
					'output' => 'system',
					'to' => 'assistant',
					'input' => 'system'
				],
				[
					'from' => '__input__',
					'output' => 'prompt',
					'to' => 'assistant',
					'input' => 'prompt'
				]
			]
		];
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
