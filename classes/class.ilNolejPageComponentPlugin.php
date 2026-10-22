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
 * NolejPageComponent Page Component Plugin.
 */
class ilNolejPageComponentPlugin extends ilPageComponentPlugin
{
    /** @var string */
    /** @var string */
    public const PLUGIN_ID = "pcnlj";

    /** @var string */
    public const PLUGIN_DIR = "./Customizing/global/plugins/Services/COPage/PageComponent/NolejPageComponent";

    /**
     * Check whether the Nolej repository object plugin (and its classes
     * used by this page component) is installed and loadable.
     * @return bool
     */
    public static function isNolejAvailable(): bool
    {
        if (
            !class_exists("ilNolejPlugin")
            || !class_exists("ilObjNolejGUI")
            || !class_exists("ilNolejManagerGUI")
        ) {
            return false;
        }

        try {
            return ilNolejPlugin::getInstance()->isActive();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check whether the current user has read access to a Nolej object.
     * @param int $ref_id
     * @return bool
     */
    public static function canReadNolejObject(int $ref_id): bool
    {
        global $DIC;

        if ($ref_id <= 0 || ilObject::_lookupType($ref_id, true) !== ilNolejPlugin::PLUGIN_ID) {
            return false;
        }

        return $DIC->access()->checkAccess("read", "", $ref_id);
    }

    /**
     * Check whether the current user has read access to at least one
     * Nolej object that owns the given document.
     * @param string $document_id
     * @return bool
     */
    public static function canReadNolejDocument(string $document_id): bool
    {
        global $DIC;

        $db = $DIC->database();
        $result = $db->queryF(
            "SELECT id FROM " . ilNolejPlugin::TABLE_DATA . " WHERE document_id = %s",
            ["string"],
            [$document_id]
        );

        while (($row = $db->fetchAssoc($result)) !== null) {
            foreach (ilObject::_getAllReferences((int) $row["id"]) as $ref_id) {
                if (self::canReadNolejObject((int) $ref_id)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check whether the H5P plugin classes needed to render Nolej activities are loadable.
     * @return bool
     */
    public static function isH5PAvailable(): bool
    {
        return class_exists("ilH5PPlugin")
            && trait_exists("srag\\Plugins\\H5P\\Content\\ContentEditorHelper")
            && class_exists("ilNolejH5PIntegrationGUI");
    }

    /**
     * Get plugin name.
     * @return string
     */
    public function getPluginName(): string
    {
        return "NolejPageComponent";
    }

    /**
     * Check if the current parent type ("auth", "cat", "crs", etc.) is
     * valid for this page component.
     *
     * @param string $a_type parent type
     * @return bool
     */
    public function isValidParentType(string $a_type): bool
    {
        // $a_type can be auth, cat, crs, ...
        return true;
    }

    /**
     * This function is called when the page content is cloned.
     * @param array<array-key, mixed> $a_properties properties saved in the page, (should be modified if neccessary)
     * @param string $a_plugin_version plugin version of the properties
     */
    public function onClone(array &$a_properties, string $a_plugin_version): void
    {
        // Nothing to clone.
        parent::onClone($a_properties, $a_plugin_version);
    }

    /**
     * This function is called before the page content is deleted.
     * @param array<array-key, mixed> $a_properties properties saved in the page (will be deleted afterwards)
     * @param string $a_plugin_version plugin version of the properties
     * @param bool $move_operation
     * @return void
     */
    public function onDelete(array $a_properties, string $a_plugin_version, bool $move_operation = false): void
    {
        // Nothing to delete.
        parent::onDelete($a_properties, $a_plugin_version, $move_operation);
    }
}
