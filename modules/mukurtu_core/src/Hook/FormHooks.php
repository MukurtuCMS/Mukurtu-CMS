<?php

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\og\Og;
use Drupal\og\OgMembershipInterface;
use Drupal\user\Entity\User;

/**
 * Hook implementations for mukurtu_core forms.
 */
class FormHooks
{
    /**
     * Implements hook_block_view_alter().
     *
     * Clears the Gin help block on the admin user register form so the generic
     * Drupal core help text ("This web page allows administrators to register
     * new users…") does not appear.
     */
    #[Hook("block_view_alter")]
    public function blockViewAlter(
        array &$build,
        BlockPluginInterface $block,
    ): void {
        if ($block->getPluginId() !== "help_block") {
            return;
        }
        if (\Drupal::routeMatch()->getRouteName() === "user.admin_create") {
            $build = [];
        }
    }

    /**
     * Implements hook_form_FORM_ID_alter() for 'language_content_settings_form'.
     *
     * Hides og_group fields from the translation settings form to prevent users
     * from marking og_group bundle fields as translatable.
     *
     * This hook must run after the content_translation module's form alter hook
     * (\Drupal\content_translation\Hook\ContentTranslationHooks::formLanguageContentSettingsFormAlter)
     * which adds the field translation checkboxes via
     * _content_translation_form_language_content_settings_form_alter().
     */
    #[
        Hook(
            "form_language_content_settings_form_alter",
            order: new OrderAfter(["content_translation"]),
        ),
    ]
    public function formLanguageContentSettingsFormAlter(
        array &$form,
        FormStateInterface $form_state,
    ): void {
        // Check if the settings array exists.
        if (empty($form["settings"])) {
            return;
        }

        // Loop through all entity types in the form.
        foreach (Element::children($form["settings"]) as $entity_type_id) {
            // Loop through all bundles for this entity type.
            foreach (
                Element::children($form["settings"][$entity_type_id])
                as $bundle
            ) {
                if (
                    empty($form["settings"][$entity_type_id][$bundle]["fields"])
                ) {
                    continue;
                }
                // Loop through all fields for this bundle.
                foreach (
                    Element::children(
                        $form["settings"][$entity_type_id][$bundle]["fields"],
                    )
                    as $field_name
                ) {
                    // Hide the og_group field from translation settings.
                    if ($field_name !== "og_group") {
                        continue;
                    }
                    unset(
                        $form["settings"][$entity_type_id][$bundle]["fields"][
                            $field_name
                        ],
                    );

                    // Also hide any column settings for the og_group field if they exist.
                    if (
                        isset(
                            $form["settings"][$entity_type_id][$bundle][
                                "columns"
                            ][$field_name],
                        )
                    ) {
                        unset(
                            $form["settings"][$entity_type_id][$bundle][
                                "columns"
                            ][$field_name],
                        );
                    }
                }
            }
        }
    }

    /**
     * Implements hook_form_FORM_ID_alter() for 'user-register-form' and
     * 'user-form'.
     *
     * Hides 'Administrator' option from the Roles selection for Mukurtu Managers
     * so that they cannot assign the admin role.
     */
    #[Hook("form_user_register_form_alter")]
    public function formUserRegisterFormAlter(
        array &$form,
        FormStateInterface $form_state,
    ): void {
        $currentUser = \Drupal::currentUser()->getAccount();
        /** @var \Drupal\Core\Session\UserSession $currentUser */
        if ($currentUser->hasRole("mukurtu_manager")) {
            if (isset($form["account"]["roles"]["#options"]["administrator"])) {
                unset($form["account"]["roles"]["#options"]["administrator"]);
            }
        }

        // Move display name field to sit directly below password in the account group.
        if (isset($form["account"]["pass"])) {
            $form["account"]["pass"]["#weight"] = 0.0012;
        }
        if (isset($form["field_display_name"])) {
            $form["account"]["field_display_name"] =
                $form["field_display_name"];
            $form["account"]["field_display_name"]["#weight"] = 0.0013;
            unset($form["field_display_name"]);
        }

        if (isset($form["account"]["notify"])) {
            unset($form["account"]["notify"]["#description"]);
        }
        if (isset($form["account"]["mail"])) {
            $form["account"]["mail"]["#description"] = t(
                "Email addresses must be unique. The email address is not made public. It will only be used to contact the user about their account or for opted-in notifications.",
            );
        }
        if (isset($form["account"]["name"])) {
            $form["account"]["name"]["#description"] = t(
                "Usernames must be unique. Several special characters are allowed, including space, period (.), hyphen (-), apostrophe ('), underscore (_), and the @ sign.",
            );
        }
        if (isset($form["account"]["status"])) {
            $form["account"]["status"]["#options"] = [
                1 => t("Active"),
                "pending" => t("Pending"),
                0 => t("Blocked"),
            ];
            array_unshift($form["#submit"], [static::class, "userStatusPreSaveSubmit"]);
            $form["#submit"][] = [static::class, "userStatusPostSaveSubmit"];
        }
        if (isset($form["account"]["roles"]["#options"])) {
            $desiredOrder = [
                "authenticated",
                "mukurtu_roundtrip_manager",
                "mukurtu_manager",
                "administrator",
            ];
            $existing = $form["account"]["roles"]["#options"];
            $reordered = [];
            foreach ($desiredOrder as $rid) {
                if (isset($existing[$rid])) {
                    $reordered[$rid] = $existing[$rid];
                }
            }
            foreach ($existing as $rid => $label) {
                if (!isset($reordered[$rid])) {
                    $reordered[$rid] = $label;
                }
            }
            $form["account"]["roles"]["#options"] = $reordered;
        }

        // The notify section is only useful to authenticated users with create
        // access; skip it for anonymous self-registration.
        $currentAccount = \Drupal::currentUser();
        if ($currentAccount->isAnonymous()) {
            return;
        }

        $entityTypeManager = \Drupal::entityTypeManager();
        $protocolStorage = $entityTypeManager->getStorage("protocol");

        // Site admins see all communities/protocols. Other privileged users (e.g.
        // Mukurtu Managers) are limited to communities they actively manage so
        // that community/protocol names aren't exposed beyond their membership.
        if ($currentAccount->hasPermission("administer users")) {
            $communityEntities = $entityTypeManager
                ->getStorage("community")
                ->loadMultiple();
        } else {
            $userEntity = User::load($currentAccount->id());
            $memberships = array_filter(
                Og::getMemberships($userEntity),
                fn($m) => $m->getGroupBundle() === "community",
            );
            $managerMemberships = array_filter(
                $memberships,
                fn($m) => $m->hasPermission("manage members"),
            );
            $communityEntities = array_filter(
                array_map(fn($m) => $m->getGroup(), $managerMemberships),
            );
        }

        $communityOptions = [];
        $protocolsByCommunity = [];
        $membershipProtocolsByCommunity = [];
        foreach ($communityEntities as $community) {
            $communityOptions[$community->id()] = $community->getName();
            $communityProtocolIds = $protocolStorage
                ->getQuery()
                ->condition("field_communities", $community->id())
                ->accessCheck(false)
                ->execute();
            $communityProtocols = [];
            foreach (
                $protocolStorage->loadMultiple($communityProtocolIds)
                as $protocol
            ) {
                $communityProtocols[$protocol->id()] = $protocol->getName();
            }
            asort($communityProtocols);
            if (!empty($communityProtocols)) {
                $protocolsByCommunity[$community->id()] = [
                    "label" => $community->getName(),
                    "protocols" => $communityProtocols,
                ];
                $membershipProtocolsByCommunity[
                    $community->id()
                ] = $communityProtocols;
            }
        }
        asort($communityOptions);
        uasort(
            $protocolsByCommunity,
            fn($a, $b) => strcmp($a["label"], $b["label"]),
        );

        $form["notify"] = [
            "#type" => "details",
            "#title" => t("Notify other users of new account"),
            "#description" => t(
                "You can choose to notify other users about the creation of this new account. This is useful if you think the user may need to be enrolled in additional communities and/or protocols. If you choose to notify other users, they will receive an email with the new account username and a link to the user profile.",
            ),
            "#open" => false,
            "#attached" => ["library" => ["mukurtu_core/notify-form"]],
        ];

        $form["notify"]["notify_all_managers"] = [
            "#type" => "checkbox",
            "#title" => t("Notify all Mukurtu Managers"),
            "#default_value" => false,
        ];

        if (!empty($communityOptions)) {
            $form["notify"]["notify_communities"] = [
                "#type" => "checkboxes",
                "#title" => t(
                    "Notify all community managers in the following communities:",
                ),
                "#options" => $communityOptions,
                "#required" => false,
                "#attributes" => ["class" => ["notify-form-checkboxes"]],
            ];
        }

        if (!empty($protocolsByCommunity)) {
            $protocols_label_id = "notify-protocols-label";
            $form["notify"]["notify_protocols"] = [
                "#type" => "container",
                "#attributes" => [
                    "class" => ["notify-protocols-wrapper"],
                    "role" => "group",
                    "aria-labelledby" => $protocols_label_id,
                ],
            ];
            $form["notify"]["notify_protocols"]["title"] = [
                "#markup" =>
                    '<p id="' .
                    $protocols_label_id .
                    '" class="fieldset__label fieldset__label--group">' .
                    t(
                        "Notify all protocol stewards in the following protocols:",
                    ) .
                    "</p>",
            ];

            foreach ($protocolsByCommunity as $communityId => $data) {
                $form["notify"]["notify_protocols"][$communityId] = [
                    "#type" => "checkboxes",
                    "#title" => $data["label"],
                    "#options" => $data["protocols"],
                    "#attributes" => ["class" => ["notify-form-checkboxes"]],
                ];
            }
        }

        $notifyUserCount = $form_state->get("notify_user_count") ?? 1;
        $users_label_id = "notify-users-label";

        $form["notify"]["notify_users"] = [
            "#type" => "container",
            "#tree" => true,
            "#prefix" =>
                '<div id="notify-users-wrapper" role="group" aria-labelledby="' .
                $users_label_id .
                '" aria-live="polite"><p id="' .
                $users_label_id .
                '" class="fieldset__label fieldset__label--group">' .
                t("Notify specific users:") .
                "</p>",
            "#suffix" => "</div>",
        ];

        for ($i = 0; $i < $notifyUserCount; $i++) {
            $form["notify"]["notify_users"]["user_" . $i] = [
                "#type" => "entity_autocomplete",
                "#target_type" => "user",
                // Numbered labels give screen reader users positional context when
                // multiple fields exist.
                "#title" => t("User @num", ["@num" => $i + 1]),
                "#title_display" => "invisible",
                "#selection_handler" => "mukurtu_manager_users",
                "#required" => false,
            ];
        }

        $form["notify"]["notify_users"]["add_more"] = [
            "#type" => "submit",
            "#value" => t("Add another user"),
            "#submit" => [[self::class, "addMoreNotifyUser"]],
            "#ajax" => [
                "callback" => [self::class, "addMoreNotifyUserCallback"],
                "wrapper" => "notify-users-wrapper",
            ],
            "#limit_validation_errors" => [],
        ];

        $form["actions"]["submit"]["#submit"][] = [
            self::class,
            "userRegisterNotifySubmit",
        ];

        // Membership section — community and protocol role assignment.
        $roleManager = \Drupal::service("og.role_manager");

        $communityRolesRaw = $roleManager->getRolesByBundle(
            "community",
            "community",
        );
        $communityRoles = [];
        foreach ($communityRolesRaw as $roleValue) {
            if (
                $roleValue->getName() !== "non-member" &&
                $roleValue->getName() !== "member"
            ) {
                $communityRoles[$roleValue->getName()] = $roleValue->getLabel();
            }
        }

        $protocolRolesRaw = $roleManager->getRolesByBundle(
            "protocol",
            "protocol",
        );
        $protocolRoles = [];
        foreach ($protocolRolesRaw as $roleValue) {
            if (
                $roleValue->getName() !== "non-member" &&
                $roleValue->getName() !== "member"
            ) {
                $protocolRoles[$roleValue->getName()] = $roleValue->getLabel();
            }
        }

        $form["membership"] = [
            "#type" => "fieldset",
            "#title" => t("Community and Protocol Membership"),
            "#tree" => true,
        ];

        foreach ($communityOptions as $communityId => $communityName) {
            $form["membership"][$communityId] = [
                "#type" => "details",
                "#title" => $communityName,
                "#open" => true,
            ];
            $form["membership"][$communityId]["community_roles"] = [
                "#type" => "checkboxes",
                "#title" => t("Community Roles"),
                "#options" => $communityRoles,
            ];

            if (!empty($membershipProtocolsByCommunity[$communityId])) {
                $statesConditions = [];
                foreach (array_keys($communityRoles) as $roleName) {
                    $statesConditions[] = [
                        ':input[name="membership[' .
                        $communityId .
                        "][community_roles][" .
                        $roleName .
                        ']"]' => ["checked" => true],
                    ];
                }
                $form["membership"][$communityId]["protocols"] = [
                    "#type" => "container",
                    "#tree" => true,
                    "#states" => ["visible" => $statesConditions],
                    "#prefix" => '<div aria-live="polite">',
                    "#suffix" => "</div>",
                ];
                $form["membership"][$communityId]["protocols"]["hint"] = [
                    "#markup" =>
                        "<p>" .
                        t("Select one or more protocol roles below.") .
                        "</p>",
                ];
                foreach (
                    $membershipProtocolsByCommunity[$communityId]
                    as $protocolId => $protocolName
                ) {
                    $form["membership"][$communityId]["protocols"][
                        $protocolId
                    ] = [
                        "#type" => "details",
                        "#title" => $protocolName,
                        "#open" => false,
                    ];
                    $form["membership"][$communityId]["protocols"][$protocolId][
                        "protocol_roles"
                    ] = [
                        "#type" => "checkboxes",
                        "#title" => t("Protocol Roles"),
                        "#options" => $protocolRoles,
                    ];
                }
            }
        }

        $form_state->set(
            "membershipCommunitiesWithProtocols",
            array_keys($membershipProtocolsByCommunity),
        );
        $form["actions"]["submit"]["#submit"][] = [
            self::class,
            "userRegisterMembershipSubmit",
        ];
    }

    /**
     * Submit handler that sends notifications after a new user account is created.
     */
    public static function userRegisterNotifySubmit(
        array &$form,
        FormStateInterface $form_state,
    ): void {
        $notifyUids = mukurtu_notifications_extract_notify_uids($form_state);
        if (!empty($notifyUids)) {
            $new_user = $form_state->getFormObject()->getEntity();
            mukurtu_notifications_notify_new_account_created(
                $new_user,
                $notifyUids,
            );
        }
    }

    /**
     * AJAX submit handler to add another user autocomplete field.
     */
    public static function addMoreNotifyUser(
        array &$form,
        FormStateInterface $form_state,
    ): void {
        $count = $form_state->get("notify_user_count") ?? 1;
        $form_state->set("notify_user_count", $count + 1);
        $form_state->setRebuild();
    }

    /**
     * AJAX callback to return the updated notify_users container.
     */
    public static function addMoreNotifyUserCallback(
        array &$form,
        FormStateInterface $form_state,
    ): array {
        return $form["notify"]["notify_users"];
    }

    /**
     * Validate handler for membership assignment on the admin user register form.
     *
     * If a community role is selected in a community that has protocols, at least
     * one protocol role must also be selected.
     *
     * This method is intentionally NOT registered on the admin user register form
     * ($form['#validate']) because membership is optional there — an admin can
     * create a user without assigning any community or protocol roles. It is kept
     * here for symmetry with CommunityManagerUserCreationForm and in case stricter
     * validation is needed on a future form that reuses the membership section.
     */
    public static function userRegisterMembershipValidate(
        array &$form,
        FormStateInterface $form_state,
    ): void {
        $membership = $form_state->getValue("membership") ?? [];
        $communitiesWithProtocols =
            $form_state->get("membershipCommunitiesWithProtocols") ?? [];

        foreach ($communitiesWithProtocols as $communityId) {
            $communityData = $membership[$communityId] ?? [];
            if (empty(array_filter($communityData["community_roles"] ?? []))) {
                continue;
            }
            $hasProtocolRole = false;
            foreach ($communityData["protocols"] ?? [] as $protocolData) {
                if (
                    !empty(array_filter($protocolData["protocol_roles"] ?? []))
                ) {
                    $hasProtocolRole = true;
                    break;
                }
            }
            if (!$hasProtocolRole) {
                $communityName =
                    $form["membership"][$communityId]["#title"] ?? $communityId;
                $form_state->setError(
                    $form["membership"][$communityId],
                    t(
                        "Please assign the new user at least one protocol role in %community.",
                        ["%community" => $communityName],
                    ),
                );
            }
        }
    }

    /**
     * Submit handler that assigns community and protocol memberships after a new
     * user account is created via the admin user register form.
     */
    public static function userRegisterMembershipSubmit(
        array &$form,
        FormStateInterface $form_state,
    ): void {
        $values = $form_state->getValues();
        if (empty($values["membership"])) {
            return;
        }

        $user = $form_state->getFormObject()->getEntity();
        $entityTypeManager = \Drupal::entityTypeManager();

        try {
            foreach ($values["membership"] as $communityId => $communityData) {
                $communityRoles = array_keys(
                    array_filter($communityData["community_roles"] ?? []),
                );
                if (empty($communityRoles)) {
                    continue;
                }
                $community = $entityTypeManager
                    ->getStorage("community")
                    ->load($communityId);
                if (!$community) {
                    continue;
                }
                $community->addMember($user, $communityRoles);

                foreach (
                    $communityData["protocols"] ?? []
                    as $protocolId => $protocolData
                ) {
                    $protocolRoles = array_keys(
                        array_filter($protocolData["protocol_roles"] ?? []),
                    );
                    if (empty($protocolRoles)) {
                        continue;
                    }
                    $protocol = $entityTypeManager
                        ->getStorage("protocol")
                        ->load($protocolId);
                    if (!$protocol) {
                        continue;
                    }
                    $protocol->addMember($user, $protocolRoles);
                }
            }
        } catch (\Throwable $e) {
            \Drupal::logger("mukurtu_core")->error(
                "Error assigning memberships for new user @name: @message",
                [
                    "@name" => $user->getAccountName(),
                    "@message" => $e->getMessage(),
                ],
            );
            \Drupal::messenger()->addWarning(
                t(
                    'The account was created but some membership assignments may not have completed. Please review the memberships for <a href=":url">%name</a>.',
                    [
                        ":url" => $user->toUrl()->toString(),
                        "%name" => $user->getAccountName(),
                    ],
                ),
            );
        }
    }

    /**
     * Implements hook_form_FORM_ID_alter() for 'user-form'.
     *
     * Hides 'Administrator' option from the Roles selection for Mukurtu Managers
     * so that they cannot assign the admin role.
     */
    #[Hook("form_user_form_alter")]
    public function formUserFormAlter(
        array &$form,
        FormStateInterface $form_state,
    ): void {
        $currentUser = \Drupal::currentUser()->getAccount();
        /** @var \Drupal\Core\Session\UserSession $currentUser */
        if ($currentUser->hasRole("mukurtu_manager")) {
            if (isset($form["account"]["roles"]["#options"]["administrator"])) {
                unset($form["account"]["roles"]["#options"]["administrator"]);
            }
        }

        // Move display name field to sit directly below password in the account group.
        if (isset($form["account"]["pass"])) {
            $form["account"]["pass"]["#weight"] = 0.0012;
        }
        if (isset($form["field_display_name"])) {
            $form["account"]["field_display_name"] =
                $form["field_display_name"];
            $form["account"]["field_display_name"]["#weight"] = 0.0013;
            unset($form["field_display_name"]);
        }

        if (isset($form["account"]["status"])) {
            $form["account"]["status"]["#options"] = [
                1 => t("Active"),
                "pending" => t("Pending"),
                0 => t("Blocked"),
            ];
            // Pre-select "Pending" when editing a currently-pending user.
            $entity = $form_state->getFormObject()->getEntity();
            if (
                !$entity->get("status")->value
                && $entity->hasField("field_pending")
                && $entity->get("field_pending")->value
            ) {
                $form["account"]["status"]["#default_value"] = "pending";
            }
            array_unshift($form["#submit"], [static::class, "userStatusPreSaveSubmit"]);
            $form["#submit"][] = [static::class, "userStatusPostSaveSubmit"];
        }

        if (isset($form["actions"]["delete"]["#title"])) {
            $form["actions"]["delete"]["#title"] = t(
                "Block or delete account",
            );
        }
    }

    /**
     * Maps the "pending" radio value to status=0 before the entity is saved.
     *
     * Runs as the first submit handler so the entity builder sees status=0 for
     * both Pending and Blocked selections.
     */
    public static function userStatusPreSaveSubmit(
        array $form,
        FormStateInterface &$form_state,
    ): void {
        $val = $form_state->getValue(["account", "status"]);
        $form_state->set("mukurtu_status_selection", $val);
        if ($val === "pending") {
            // Map the virtual "pending" option to the underlying blocked status
            // so the entity builder stores status=0. field_pending is set in
            // the post-save handler below.
            $form_state->setValue(["account", "status"], 0);
        }
    }

    /**
     * Sets field_pending=1 on the saved entity when "Pending" was selected.
     *
     * Runs after the entity save so the entity ID is available.
     */
    public static function userStatusPostSaveSubmit(
        array $form,
        FormStateInterface $form_state,
    ): void {
        if ($form_state->get("mukurtu_status_selection") === "pending") {
            $entity = $form_state->getFormObject()->getEntity();
            if ($entity && $entity->hasField("field_pending")) {
                $entity->set("field_pending", 1);
                $entity->save();
            }
        }
    }

    /**
     * Implements hook_form_FORM_ID_alter() for 'user_cancel_confirm_form'.
     *
     * Updates the single-user cancel confirm form title and submit button to
     * reflect that both blocking and deletion are available.
     */
    #[Hook("form_user_cancel_form_alter")]
    public function formUserCancelFormAlter(
        array &$form,
        FormStateInterface $form_state,
    ): void {
        $entity = $form_state->getFormObject()->getEntity();
        $own_account = $entity->id() == \Drupal::currentUser()->id();
        $form["#title"] = $own_account
            ? t("Are you sure you want to block or delete your account?")
            : t(
                "Are you sure you want to block or delete the account %name?",
                ["%name" => $entity->label()],
            );
        if (isset($form["user_cancel_method"]["#title"])) {
            $form["user_cancel_method"]["#title"] = t(
                "Block or delete options:",
            );
        }
        $this->relabelCancelMethods($form);
        if (isset($form["actions"]["submit"])) {
            $form["actions"]["submit"]["#value"] = t("Block or delete account");
        }
    }

    /**
     * Implements hook_form_alter() for OG membership add/edit forms.
     *
     * Removes "Pending" from the membership state options so that only Active
     * and Blocked are available when managing group memberships.
     */
    #[Hook("form_alter")]
    public function formAlterRemoveOgPendingState(
        array &$form,
        FormStateInterface $form_state,
        string $form_id,
    ): void {
        if (!preg_match('/^og_membership_.+_(add|edit)_form$/', $form_id)) {
            return;
        }
        if (
            isset(
                $form["state"]["widget"]["#options"][
                    OgMembershipInterface::STATE_PENDING
                ],
            )
        ) {
            unset(
                $form["state"]["widget"]["#options"][
                    OgMembershipInterface::STATE_PENDING
                ],
            );
        }
    }

    /**
     * Prevents a user from applying bulk actions to their own account.
     *
     * Disables the current user's checkbox in the standard user admin bulk form
     * via #after_build so the entry is never submitted. The VBO-based
     * mukurtu_people view is protected at the action access() level instead.
     */
    #[Hook("form_alter")]
    public function formAlterPreventSelfBlock(
        array &$form,
        FormStateInterface $form_state,
        string $form_id,
    ): void {
        if (!str_starts_with($form_id, "views_form_user_admin_people_")) {
            return;
        }
        if (isset($form["user_bulk_form"])) {
            $form["user_bulk_form"]["#after_build"][] = [
                static::class,
                "disableSelfBulkFormCheckbox",
            ];
        }
    }

    /**
     * After-build callback: disables the bulk form checkbox for the current user.
     *
     * BulkForm key format: base64(json([langcode, entity_id])) or
     * base64(json([langcode, entity_id, revision_id])).
     */
    public static function disableSelfBulkFormCheckbox(
        array $element,
        FormStateInterface $form_state,
    ): array {
        $current_uid = \Drupal::currentUser()->id();
        foreach (Element::children($element) as $key) {
            $value = $element[$key]["#return_value"] ?? null;
            if (!$value) {
                continue;
            }
            $key_parts = json_decode(base64_decode($value), true);
            if (!is_array($key_parts) || !isset($key_parts[1])) {
                continue;
            }
            $entity_id = $key_parts[1];
            if ($entity_id == $current_uid) {
                $element[$key]["#disabled"] = true;
                $element[$key]["#attributes"]["title"] = t(
                    "You cannot apply bulk actions to your own account.",
                );
                break;
            }
        }
        return $element;
    }

    /**
     * Implements hook_form_alter().
     *
     * Warns editors on content add forms when no Cultural Protocols exist yet.
     * Protocols are required to control access to content; without at least one,
     * newly created items cannot be properly shared.
     */
    #[Hook("form_alter")]
    public function formAlterWarnNoProtocols(
        array &$form,
        FormStateInterface $form_state,
        string $form_id,
    ): void {
        $affected_forms = [
            "node_digital_heritage_form",
            "node_collection_form",
            "node_dictionary_word_form",
            "node_word_list_form",
            "node_person_form",
            "node_place_form",
        ];

        if (!in_array($form_id, $affected_forms, true)) {
            return;
        }

        $node = $form_state->getFormObject()->getEntity();
        if (!$node->isNew()) {
            return;
        }

        $protocol_count = \Drupal::entityQuery("protocol")
            ->accessCheck(false)
            ->count()
            ->execute();

        if ($protocol_count > 0) {
            return;
        }

        $communities_url = Url::fromUri("internal:/admin/communities-protocols");
        $link = \Drupal::service("link_generator")->generate(
            t("communities and cultural protocols"),
            $communities_url,
        );
        $message = t(
            "You do not have permission to create content in any cultural protocols, which is a requirement for all content. If you think you should have access to existing protocol(s), contact your site administrator. If you are the site administrator, ensure that you have created appropriate @link before creating content.",
            ["@link" => $link],
        );

        $form["no_protocols_warning"] = [
            "#type" => "markup",
            "#markup" =>
                '<div class="messages messages--warning" role="alert">' .
                $message .
                "</div>",
            "#weight" => -100,
        ];
    }

    /**
     * Removes message_digest notification actions from the user admin bulk form.
     *
     * These come from message_digest_ui optional config and should not be
     * exposed in Mukurtu's user management UI. This hook acts as a safety net
     * alongside the composer patch and update hook 40004: if the patch fails to
     * apply on a given environment the actions are still hidden from the UI.
     */
    #[Hook("form_alter")]
    public function formAlterRemoveNotificationBulkActions(
        array &$form,
        FormStateInterface $form_state,
        string $form_id,
    ): void {
        $is_user_form =
            str_starts_with($form_id, "views_form_user_admin_people_") ||
            str_starts_with($form_id, "views_form_mukurtu_people_");
        $is_members_form = str_starts_with(
            $form_id,
            "views_form_og_members_overview_",
        );

        if (!$is_user_form && !$is_members_form) {
            return;
        }

        $actions_to_remove = [
            "message_digest_interval.email_user.immediate",
            "message_digest_interval.email_user.daily",
            "message_digest_interval.email_user.weekly",
            "og_membership_approve_pending_action",
            "og_membership_pending_action",
            "mukurtu_block_user_action",
            "user_block_user_action",
        ];

        if (isset($form["header"]["user_bulk_form"]["action"]["#options"])) {
            foreach ($actions_to_remove as $action_id) {
                unset(
                    $form["header"]["user_bulk_form"]["action"]["#options"][
                        $action_id
                    ],
                );
            }
            if (
                isset(
                    $form["header"]["user_bulk_form"]["action"]["#options"][
                        "user_cancel_user_action"
                    ],
                )
            ) {
                $form["header"]["user_bulk_form"]["action"]["#options"][
                    "user_cancel_user_action"
                ] = t("Block or delete the selected user account(s)");
            }
        }

        if (
            isset(
                $form["header"]["og_membership_bulk_form"]["action"]["#options"],
            )
        ) {
            foreach ($actions_to_remove as $action_id) {
                unset(
                    $form["header"]["og_membership_bulk_form"]["action"][
                        "#options"
                    ][$action_id],
                );
            }
            if (
                isset(
                    $form["header"]["og_membership_bulk_form"]["action"][
                        "#options"
                    ]["og_membership_delete_action"],
                )
            ) {
                $form["header"]["og_membership_bulk_form"]["action"][
                    "#options"
                ]["og_membership_delete_action"] = t("Remove from group");
            }
        }
    }

    /**
     * Implements hook_form_FORM_ID_alter() for 'user_multiple_cancel_confirm'.
     *
     * Changes the bulk cancel confirmation form title and relabels "Disable"
     * options to "Block" so terminology matches what the action actually does.
     */
    #[Hook("form_user_multiple_cancel_confirm_alter")]
    public function formUserMultipleCancelConfirmAlter(
        array &$form,
        FormStateInterface $form_state,
    ): void {
        $form["#title"] = t("Block or delete selected user account(s)");
        if (isset($form["user_cancel_method"]["#title"])) {
            $form["user_cancel_method"]["#title"] = t(
                "Block or delete options:",
            );
        }
        if (isset($form["user_cancel_method_show"]["#title"])) {
            $form["user_cancel_method_show"]["#title"] = t(
                "Block or delete options:",
            );
        }
        $this->relabelCancelMethods($form);
    }

    /**
     * Implements hook_form_FORM_ID_alter() for 'user_admin_settings'.
     *
     * Updates the account settings page to use "delete or block" language and
     * replaces "Disable the account" option descriptions with "Block the account".
     */
    #[Hook("form_user_admin_settings_alter")]
    public function formUserAdminSettingsAlter(
        array &$form,
        FormStateInterface $form_state,
    ): void {
        if (
            isset(
                $form["registration_cancellation"]["user_cancel_method"][
                    "#title"
                ],
            )
        ) {
            $form["registration_cancellation"]["user_cancel_method"][
                "#title"
            ] = t("Default option when blocking or deleting a user account:");
        }
        if (isset($form["registration_cancellation"])) {
            $this->relabelCancelMethods($form["registration_cancellation"]);
        } else {
            $this->relabelCancelMethods($form);
        }
    }

    /**
     * Replaces "Disable the account" with "Block the account" in cancel method
     * radio option descriptions wherever they appear in a form subtree.
     */
    private function relabelCancelMethods(array &$element): void {
        $replacements = [
            "user_cancel_block" => t(
                "Block the user account(s), do not change their content.",
            ),
            "user_cancel_block_unpublish" => t(
                "Block the user account(s) and unpublish their content.",
            ),
            "user_cancel_reassign" => t(
                "Delete the user account(s), keep their content and assign it to the Anonymous user account. This cannot be undone.",
            ),
            "user_cancel_delete" => t(
                "Delete the user account(s) and their content. This cannot be undone and is high risk.",
            ),
        ];
        foreach ($replacements as $key => $label) {
            if (isset($element["user_cancel_method"]["#options"][$key])) {
                $element["user_cancel_method"]["#options"][$key] = $label;
            }
        }
    }

    /**
     * Implements hook_entity_operation_alter().
     *
     * Relabels the "Delete" operation on OG memberships to "Remove from group".
     */
    #[Hook("entity_operation_alter")]
    public function entityOperationAlter(
        array &$operations,
        EntityInterface $entity,
    ): void {
        if ($entity->getEntityTypeId() !== "og_membership") {
            return;
        }
        if (isset($operations["delete"])) {
            $operations["delete"]["title"] = t("Remove from group");
        }
    }
}
