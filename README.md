# Base3MermaidPageComponent

Base3MermaidPageComponent provides Mermaid diagram creation as an ILIAS Page Component. It enables users to create Mermaid diagrams with support from a configured BASE3 agent and embed them directly into ILIAS content pages where Page Components are supported.

The key words "MUST", "MUST NOT", "REQUIRED", "SHALL", "SHALL NOT", "SHOULD",
"SHOULD NOT", "RECOMMENDED", "MAY", and "OPTIONAL"
in this document are to be interpreted as described in
[RFC 2119](https://www.ietf.org/rfc/rfc2119.txt).

**Table of Contents**

* [Requirements](#requirements)
* [Installation](#installation)
* [BASE3 Framework Dependency](#base3-framework-dependency)

## Requirements

* [![Minimum ILIAS Version](https://img.shields.io/badge/Minimum_ILIAS-10.0-orange.svg)](https://ilias.de/) [![Maximum ILIAS Version](https://img.shields.io/badge/Maximum_ILIAS-12.999-orange.svg)](https://ilias.de/)
* ![Plugin Slot](https://img.shields.io/badge/Slot-PageComponent-blue)
* [![Minimum PHP Version](https://img.shields.io/badge/Minimum_PHP-8.1-blue.svg)](https://php.net/) [![Maximum PHP Version](https://img.shields.io/badge/Maximum_PHP-8.4-blue.svg)](https://php.net/)

## Installation

Before installing the plugin ensure all requirements are given.
The files MUST be saved in the following directory:

```
<ILIAS>/public/Customizing/global/plugins/Services/COPage/PageComponent/Base3MermaidPageComponent
```

Correct file and folder permissions MUST be ensured by the responsible system administrator.
The plugin's files and folders SHOULD NOT be created as root.

After copying the plugin files, the plugin MUST be installed and activated in the ILIAS administration.

## BASE3 Framework Dependency

This plugin is only runnable in connection with the BASE3 Framework, the BaseIlias integration, ClientStack, AssistantFoundation, AssistantRuntime, a configured agent runtime, the corresponding dependent BASE3 components, and the Base3IliasAdapter UIHook plugin.

ClientStack provides both the `mermaiddisplay` implementation and the local `mermaid.min.js` browser bundle. A separate Mermaid BASE3 plugin is not required.

These dependencies MUST be installed, configured, and active before this plugin can be used productively.

## Configured Mermaid agent

AI generation uses the configured BASE3 agent stored under:

```text
agent/mermaid-agent
```

The Page Component does not define an agent runtime, provider, model, flow,
memory, or tool configuration. These choices belong to the configured agent.
The agent should be enabled and instructed to return only complete Mermaid
source without Markdown fences or explanatory text.

Each editor instance supplies its own `conversation_channel_id`. This allows a
configured memory profile to work without sharing conversation history between
simultaneously opened Mermaid editors.
