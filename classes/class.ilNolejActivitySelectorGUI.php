<?php

/**
 * This file is part of Nolej Page Component Plugin for ILIAS,
 * developed by OC Open Consulting to integrate ILIAS with Nolej
 * software by Neuronys.
 *
 * @author Vincenzo Padula <vincenzo@oc-group.eu>
 * @copyright 2024 OC Open Consulting SB Srl
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

/**
 * Activity selector GUI.
 * @ilCtrl_isCalledBy ilNolejActivitySelectorGUI: ilNolejPageComponentPluginGUI
 */
class ilNolejActivitySelectorGUI extends ilRepositorySelectorExplorerGUI
{
    protected ilDBInterface $db;

    protected \ILIAS\UI\Renderer $renderer;

    protected \ILIAS\UI\Factory $factory;

    protected string $target_url;

    /**
     * ilNolejActivitySelectorGUI constructor.
     * @param object|string[] $a_parent_obj parent gui class or class array
     * @param string $a_parent_cmd
     * @param object|string $a_selection_gui gui class that should be called for the selection command
     * @param string $a_selection_cmd
     * @param string $a_target_url
     */
    public function __construct(
        $a_parent_obj,
        string $a_parent_cmd,
        $a_selection_gui = null,
        string $a_selection_cmd = "",
        string $a_target_url = ""
    ) {
        global $DIC;

        parent::__construct($a_parent_obj, $a_parent_cmd, $a_selection_gui, $a_selection_cmd);

        $this->db = $DIC->database();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();

        $this->target_url = $a_target_url;

        $this->setSkipRootNode(true);
        $this->setTypeWhiteList(["root", "cat", "grp", "fold", "crs", ilNolejPlugin::PLUGIN_ID]);
        $this->setClickableTypes([ilNolejPlugin::PLUGIN_ID]);
    }

    /**
     * Init JS/CSS.
     * @param bool $new
     * @return string
     */
    public function getHTML($new = false): string
    {
        global $DIC;

        $main_tpl = $DIC->ui()->mainTemplate();

        // The UI framework scripts are registered only when a component of
        // that kind is rendered on the page; the modal and the tree are
        // rendered asynchronously, so they are loaded explicitly.
        $main_tpl->addJavaScript("assets/js/modal.min.js");
        $main_tpl->addJavaScript("assets/js/tree.js");
        $main_tpl->addJavaScript(ilNolejPageComponentPlugin::PLUGIN_DIR . "/js/activity_selector.js");

        $this->ctrl->setParameter($this, "selection_cmd", $this->selection_cmd);
        $asyncUrl = $this->ctrl->getLinkTarget($this, "insert_activityModal");
        $main_tpl->addOnLoadCode("pcnlj_setup_modal(" . json_encode($asyncUrl, JSON_THROW_ON_ERROR) . ");");

        return parent::getHTML($new);
    }

    /**
     * Set only completed Nolej objects as clickable.
     * @param array<string, mixed> $a_node
     * @return bool
     */
    public function isNodeClickable($a_node): bool
    {
        if (!parent::isNodeClickable($a_node)) {
            // Not selectable by default.
            return false;
        }

        // Check if selected object has any activity.
        $ref_id = (int) $a_node["child"];
        $objGui = new ilObjNolejGUI($ref_id);
        $manager = new ilNolejManagerGUI($objGui);

        $result = $this->db->queryF(
            "SELECT DISTINCT generated FROM " . ilNolejPlugin::TABLE_H5P
            . " WHERE document_id = %s"
            . " ORDER BY generated DESC",
            ["string"],
            [$manager->documentId]
        );

        return $this->db->numRows($result) > 0;
    }

    /**
     * Do not open a link.
     * @param array<string, mixed> $a_node
     * @return string link href.
     */
    public function getNodeHref($a_node): string
    {
        return "#";
    }

    /**
     * Open a modal to select the activity.
     * @param array<string, mixed> $a_node
     * @return string javascript onclick function.
     */
    public function getNodeOnClick($a_node): string
    {
        $ref_id = (int) $a_node["child"];
        return "event.stopPropagation(); event.preventDefault(); pcnlj_show_modal('{$ref_id}'); return false;";
    }

    /**
     * Handles all commmands, $cmd = functionName()
     * @throws ilException if command is not known
     * @return void
     */
    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd();
        switch ($cmd) {
            // The "insert_" prefix lets the request pass the creation mode of
            // ilPageEditorGUI, which accepts only commands starting with
            // "insert" or "create".
            case "insert_activityModal":
            case "activityModal":
                $this->activityModal();
                break;

            default:
                // Command not recognized.
                throw new ilException("Unknown command: '$cmd'");
        }
    }

    /**
     * Get the activities of the selected Nolej object.
     * @return void
     */
    public function activityModal(): void
    {
        global $DIC;

        $refinery = $DIC->refinery();
        $request_wrapper = $DIC->http()->wrapper()->query();

        $ref_id = $request_wrapper->has("sel_ref_id")
            ? $request_wrapper->retrieve(
                "sel_ref_id",
                $refinery->byTrying([$refinery->kindlyTo()->int(), $refinery->always(0)])
            )
            : 0;

        if (!ilNolejPageComponentPlugin::canReadNolejObject($ref_id)) {
            $plugin = $DIC["component.factory"]->getPlugin(ilNolejPageComponentPlugin::PLUGIN_ID);
            $box = $this->factory->messageBox()->failure($plugin->txt("err_no_permission"));
            $modal = $this->factory->modal()->roundtrip("", [$box]);
            echo $this->renderer->renderAsync([$modal]);
            $DIC->http()->close();
            return;
        }

        // Modal content.
        $objGui = new ilObjNolejGUI($ref_id);
        $manager = new ilNolejManagerGUI($objGui);
        $title = $objGui->getObject()?->getTitle() ?? "";
        $content = $this->initActivityForm((string) $manager->documentId);
        $modal = $this->factory->modal()->roundtrip($title, $content);

        echo $this->renderer->renderAsync([$modal]);
        $DIC->http()->close();
    }

    /**
     * Activities expandable tree.
     * @param string $documentId
     * @return \ILIAS\UI\Component\Tree\Tree
     */
    protected function initActivityForm(string $documentId): \ILIAS\UI\Component\Tree\Tree
    {
        $nolej = ilNolejPlugin::getInstance();

        $result = $this->db->queryF(
            "SELECT type, content_id, generated FROM " . ilNolejPlugin::TABLE_H5P
            . " WHERE document_id = %s"
            . " ORDER BY generated DESC",
            ["string"],
            [$documentId]
        );

        $data = [];
        $lastTimestamp = "";
        $i = -1;
        while ($row = $this->db->fetchAssoc($result)) {
            $timestamp = $row["generated"];
            if ($timestamp != $lastTimestamp) {
                $lastTimestamp = $timestamp;
                $i++;
                $datetime = ilDatePresentation::formatDate(new ilDateTime($timestamp, IL_CAL_UNIX));
                $data[$i] = [
                    "label" => $datetime,
                    "content_id" => null,
                    "children" => [],
                ];
            }

            $data[$i]["children"][] = [
                "label" => $nolej->txt("activities_" . $row["type"]),
                "content_id" => $row["content_id"],
                "children" => [],
            ];
        }

        $recursion = new class () implements \ILIAS\UI\Component\Tree\TreeRecursion {
            /**
             * @param array<string, mixed> $record
             * @param array<string, mixed>|null $environment
             * @return array<int, array<string, mixed>>
             */
            public function getChildren($record, $environment = null): array
            {
                return $record["children"];
            }

            /**
             * @param array<string, mixed> $record
             * @param array<string, mixed>|null $environment
             */
            public function build(
                \ILIAS\UI\Component\Tree\Node\Factory $factory,
                $record,
                $environment = null
            ): \ILIAS\UI\Component\Tree\Node\Node {
                $label = $record["label"];
                $node = $factory->simple($label);

                if (count($record["children"]) === 0) {
                    $node = $node->withLink(new \ILIAS\Data\URI($environment["url"] . "&content_id=" . $record["content_id"]));
                }

                if ($label === $environment["first"]) {
                    $node = $node->withExpanded(true);
                }

                return $node;
            }
        };

        return $this->factory->tree()->expandable("Label", $recursion)
            ->withEnvironment([
                "first" => $data[0]["label"] ?? "",
                "url" => ILIAS_HTTP_PATH . "/" . $this->target_url . "&document_id={$documentId}",
            ])
            ->withData($data)
            ->withHighlightOnNodeClick(true);
    }
}
