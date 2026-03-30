<?php declare(strict_types=1);

use Base3\Api\IDisplay;
use Base3\Base3Ilias\PageComponent\AbstractPageComponentPluginGUI;

/**
 * @ilCtrl_isCalledBy ilBase3MermaidPageComponentPluginGUI: ilPCPluggedGUI
 */
class ilBase3MermaidPageComponentPluginGUI extends AbstractPageComponentPluginGUI {

        protected function getPageComponentName(): string {
                return 'BASE3 Mermaid';
        }

        protected function getPageComponentDesc(): string {
                return 'BASE3 Mermaid Page Component';
        }

        protected function getDefaultProps(): array {
                return [];
        }

        protected function setFormContent(ilPropertyFormGUI $form, array $props): void {
        }

        protected function getPresentationHtml(array $a_properties, string $plugin_version): string {

                // include client scripts
                $this->mainTemplate->addJavaScript('components/Base3/ClientStack/assetloader/assetloader.min.js');

                // find display
                $displays = $this->classmap->getInstances(['interface' => IDisplay::class, 'name' => 'mermaiddisplay']);
                if (empty($displays)) return 'Display not found.';
                $display = $displays[0];

                // configure and output
		// $display->setData($a_properties);
		$data = [ "mermaid" => "flowchart TD\n\tA[Start] --> B{Entscheidung}\n\tB -->|Ja| C[Weiter]\n\tB -->|Nein| D[Abbruch]\n\tC --> E[Ende]\n\tD --> E" ];
		$display->setData($data);
                return $display->getOutput();
        }
}
