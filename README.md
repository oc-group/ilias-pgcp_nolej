# Nolej AI for ILIAS LMS Page Component
This is the companion plugin for [Nolej AI for ILIAS LMS](https://github.com/Neuronys/ilias-robj_nolej).

Generate the activities with the main plugin, and insert them in your modules with this plugin.

Note: this branch is for ILIAS 8. If you have ILIAS 6 or 7,
see [branch release_7](https://github.com/Neuronys/ilias-pgcp_nolej/tree/release_7).

## Requirements

* ILIAS 8.x - 9.x
* [Nolej AI for ILIAS LMS plugin](https://github.com/Neuronys/ilias-robj_nolej) installed and updated.

## Installation

### Download the plugin

From the ILIAS directory, run:

```sh
mkdir -p Customizing/global/plugins/Services/COPage/PageComponent
cd Customizing/global/plugins/Services/COPage/PageComponent
git clone -b release_8 https://github.com/Neuronys/ilias-pgcp_nolej NolejPageComponent
```

Return to the ILIAS directory and run:

```sh
composer du
```

### Install the plugin

1. Go into `Administration` -> `Extending ILIAS` -> `Plugins`
2. Look for the name of this plugin
3. Click on `Actions` -> `Install`
