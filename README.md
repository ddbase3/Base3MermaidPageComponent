# Base3MermaidPageComponent

Base3MermaidPageComponent provides Mermaid diagram creation as an ILIAS Page Component. It enables users to create Mermaid diagrams with support from an AI agent based on the BASE3 MissionBay agent system and embed them directly into ILIAS content pages where Page Components are supported.

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

This plugin is only runnable in connection with the BASE3 Framework, the BaseIlias integration, the corresponding dependent BASE3 components, and the Base3IliasAdapter UIHook plugin.

These dependencies MUST be installed, configured, and active before this plugin can be used productively.
