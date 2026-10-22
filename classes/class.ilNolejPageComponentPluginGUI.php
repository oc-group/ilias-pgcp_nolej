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
 * Page Component GUI.
 * @ilCtrl_isCalledBy ilNolejPageComponentPluginGUI: ilPCPluggedGUI
 * @ilCtrl_Calls ilNolejPageComponentPluginGUI: ilNolejActivitySelectorGUI
 */
class ilNolejPageComponentPluginGUI extends ilPageComponentPluginGUI
{
    public const CMD_INSERT = "insert";
    public const CMD_CREATE = "create";
    public const CMD_SAVE = "save";
    public const CMD_EDIT = "edit";
    public const CMD_UPDATE = "update";
    public const CMD_CANCEL = "cancel";

    protected ilCtrlInterface $ctrl;

    protected ilGlobalTemplateInterface $tpl;

    protected ilDBInterface $db;

    /**
     * ilNolejPageComponentPluginGUI constructor.
     */
    public function __construct()
    {
        global $DIC;

        parent::__construct();

        $this->ctrl = $DIC->ctrl();
        $this->db = $DIC->database();
        $this->tpl = $DIC->ui()->mainTemplate();

        if (ilNolejPageComponentPlugin::isNolejAvailable()) {
            $this->lng->loadLanguageModule(ilNolejPlugin::PREFIX);
        }
    }

    /**
     * Delegates incoming commands.
     * @throws ilException if command is not known
     * @return void
     */
    public function executeCommand(): void
    {
        global $DIC;

        if (!$this->plugin->isActive()) {
            throw new ilException("Plugin not active.");
        }

        if (!ilNolejPageComponentPlugin::isNolejAvailable()) {
            $this->showMissingNolej();
            return;
        }

        $next_class = $this->ctrl->getNextClass();

        switch ($next_class) {
            case strtolower(ilNolejActivitySelectorGUI::class):
                $refinery = $DIC->refinery();
                $request_wrapper = $DIC->http()->wrapper()->query();
                $selection_cmd = $request_wrapper->has("selection_cmd")
                    ? $request_wrapper->retrieve("selection_cmd", $refinery->kindlyTo()->string())
                    : "";
                $gui = $this->getNolejSelectorGUI("", $selection_cmd);
                $this->ctrl->forwardCommand($gui);
                break;

            default:
                $cmd = $this->ctrl->getCmd();
                switch ($cmd) {
                    case self::CMD_INSERT:
                    case self::CMD_CREATE:
                    case self::CMD_SAVE:
                    case self::CMD_EDIT:
                    case self::CMD_UPDATE:
                    case self::CMD_CANCEL:
                        $this->$cmd();
                        break;

                    default:
                        // Command not recognized.
                        throw new ilException("Unknown command: '$cmd'");
                }
        }
    }

    /**
     * Show a message when the Nolej plugin is not installed, then go back to the editor.
     * Used by the commands executed through executeCommand() (create, update, ...).
     * @return void
     */
    protected function showMissingNolej(): void
    {
        $this->tpl->setOnScreenMessage("failure", $this->plugin->txt("err_nolej_missing"), true);
        $this->returnToParent();
    }

    /**
     * Show a message in the page content instead of the selector form.
     * insert() and edit() are called by ilPageComponentPluginGUI::getHTML(),
     * which must return, so a redirect is not safe there.
     * @return void
     */
    protected function showMissingNolejContent(): void
    {
        global $DIC;

        $message = $DIC->ui()->renderer()->render(
            $DIC->ui()->factory()->messageBox()->failure($this->plugin->txt("err_nolej_missing"))
        );
        $this->tpl->setContent($message);
    }

    /**
     * Create new page component element.
     * @return void
     */
    public function insert(): void
    {
        if (!ilNolejPageComponentPlugin::isNolejAvailable()) {
            $this->showMissingNolejContent();
            return;
        }

        $form = $this->initSelectorForm(self::CMD_INSERT, self::CMD_CREATE);
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Save new page component element.
     * @return void
     */
    public function create(): void
    {
        if (!ilNolejPageComponentPlugin::isNolejAvailable()) {
            $this->showMissingNolej();
            return;
        }

        // Save the form data.
        $type = "failure";
        $message = "msg_obj_not_created";
        if ($this->saveData(true)) {
            $type = "success";
            $message = "msg_obj_created";
        }
        $this->tpl->setOnScreenMessage($type, $this->plugin->txt($message), true);

        // Return to the page editor.
        $this->returnToParent();
    }

    /**
     * Init the properties form and load the stored values.
     * @return void
     */
    public function edit(): void
    {
        if (!ilNolejPageComponentPlugin::isNolejAvailable()) {
            $this->showMissingNolejContent();
            return;
        }

        $form = $this->initSelectorForm(self::CMD_EDIT, self::CMD_UPDATE);
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Init selector form.
     * @param string $cmd
     * @param string $cmdToCall
     * @return ilPropertyFormGUI
     */
    protected function initSelectorForm(string $cmd = "", string $cmdToCall = ""): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $exp = $this->getNolejSelectorGUI($cmd, $cmdToCall);
        $exp->handleCommand();

        $selector = new ilNonEditableValueGUI($this->plugin->txt("select_obj"), "", true);
        $selector->setValue($exp->getHTML());
        $form->addItem($selector);

        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->addCommandButton(self::CMD_CANCEL, $this->lng->txt("cancel"));

        return $form;
    }

    /**
     * Update page component element.
     * @return void
     */
    public function update(): void
    {
        if (!ilNolejPageComponentPlugin::isNolejAvailable()) {
            $this->showMissingNolej();
            return;
        }

        // Save the form data.
        $type = "failure";
        $message = "msg_obj_not_modified";
        if ($this->saveData(false)) {
            $type = "success";
            $message = "msg_obj_modified";
        }
        $this->tpl->setOnScreenMessage($type, $this->plugin->txt($message), true);

        // Return to the page editor.
        $this->returnToParent();
    }

    /**
     * Get Nolej tree explorer.
     * @param string $cmd
     * @param string $selection_cmd
     * @return ilNolejActivitySelectorGUI
     */
    protected function getNolejSelectorGUI(string $cmd = "", string $selection_cmd = ""): ilNolejActivitySelectorGUI
    {
        $target = $this->ctrl->getLinkTarget($this, $selection_cmd);
        $tree = new ilNolejActivitySelectorGUI($this, $cmd, null, $selection_cmd, $target);
        return $tree;
    }

    /**
     * Save the form values.
     * @param bool $a_create
     * @return bool success
     */
    protected function saveData(bool $a_create): bool
    {
        global $DIC;

        $refinery = $DIC->refinery();
        $request_wrapper = $DIC->http()->wrapper()->query();

        if (!$request_wrapper->has("document_id") || !$request_wrapper->has("content_id")) {
            return false;
        }

        $documentId = $request_wrapper->retrieve("document_id", $refinery->kindlyTo()->string());
        $contentId = $request_wrapper->retrieve(
            "content_id",
            $refinery->byTrying([$refinery->kindlyTo()->int(), $refinery->always(0)])
        );

        // The content must belong to the document, and the user must be
        // allowed to read a Nolej object that owns that document.
        if (!ilNolejPageComponentPlugin::canReadNolejDocument($documentId)) {
            return false;
        }

        $result = $this->db->queryF(
            "SELECT content_id FROM " . ilNolejPlugin::TABLE_H5P
            . " WHERE document_id = %s AND content_id = %s",
            ["string", "integer"],
            [$documentId, $contentId]
        );
        if ($this->db->numRows($result) === 0) {
            return false;
        }

        $a_properties = [
            "document_id" => $documentId,
            "content_id" => $contentId,
        ];

        return $a_create
            ? $this->createElement($a_properties)
            : $this->updateElement($a_properties);
    }

    /**
     * Cancel the creation or the update and return to the editor.
     * @return void
     */
    public function cancel(): void
    {
        $this->returnToParent();
    }

    /**
     * Get HTML for page component element depending on the context.
     * @param string $a_mode page mode (edit, presentation, print, preview, offline)
     * @param array<array-key, mixed> $a_properties
     * @param string $plugin_version
     * @return string html code
     */
    public function getElementHTML(
        string $a_mode,
        array $a_properties,
        string $plugin_version
    ): string {
        global $DIC;

        $factory = $DIC->ui()->factory();
        $renderer = $DIC->ui()->renderer();

        $tpl = $this->plugin->getTemplate("default/tpl.content.html");

        if (!ilNolejPageComponentPlugin::isNolejAvailable()) {
            $missing = $renderer->render($factory->messageBox()->info($this->plugin->txt("err_nolej_missing")));
            $tpl->setVariable("CONTENT", $missing);
            return $tpl->get();
        }

        $nolej = ilNolejPlugin::getInstance();

        $error = $renderer->render($factory->messageBox()->failure($this->plugin->txt("err_not_found")));

        if (!isset($a_properties["content_id"])) {
            $tpl->setVariable("CONTENT", $error);
            return $tpl->get();
        }

        $contentId = (int) $a_properties["content_id"];

        if ($a_mode !== "edit") {
            $tpl->setVariable("CONTENT", $this->renderActivity($contentId));
            return $tpl->get();
        }

        $result = $this->db->queryF(
            "SELECT d.title, c.type"
            . " FROM " . ilNolejPlugin::TABLE_DOC . " d"
            . " INNER JOIN " . ilNolejPlugin::TABLE_H5P . " c"
            . " ON c.document_id = d.document_id"
            . " WHERE c.content_id = %s",
            ["integer"],
            [$contentId]
        );

        $row = $this->db->fetchAssoc($result);
        if ($row === null) {
            $tpl->setVariable("CONTENT", $error);
            return $tpl->get();
        }

        $type = (string) ($row["type"] ?? "");
        $typeLabel = $type !== "" ? $nolej->txt("activities_" . $type) : "";
        $tpl->touchBlock("title");
        $tpl->setVariable("TITLE", sprintf($nolej->txt("activities_selected"), $typeLabel, $row["title"]));
        $tpl->setVariable("CONTENT", $this->renderActivity($contentId));

        return $tpl->get();
    }

    /**
     * Render the H5P activity, or a message box when H5P is missing or rendering fails.
     * @param int $contentId
     * @return string html code
     */
    protected function renderActivity(int $contentId): string
    {
        global $DIC;

        $factory = $DIC->ui()->factory();
        $renderer = $DIC->ui()->renderer();

        if (!ilNolejPageComponentPlugin::isH5PAvailable()) {
            return $renderer->render($factory->messageBox()->info($this->plugin->txt("err_h5p_missing")));
        }

        try {
            return ilNolejPlugin::renderH5P($contentId);
        } catch (\Throwable) {
            return $renderer->render($factory->messageBox()->failure($this->plugin->txt("err_not_found")));
        }
    }
}
