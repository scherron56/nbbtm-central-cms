<?php

/**
 * include/registration_fee.php
 * Shared helper: syncs a "Registration Fee Paid" income actual for a contact's
 * registration. Used by both prg_event_api.php and self_register.php so
 * self-registration behaves identically to the admin registration flow.
 */

/**
 * Sync a "Registration Fee Paid" income actual for a contact's registration.
 * - Paid/completed  -> actual is inserted (once per contact/event)
 * - Not paid        -> any existing fee actual for that contact is removed
 */
if (!function_exists('logRegistrationFeeActual')) {
    function logRegistrationFeeActual($db, int $prgevntId, int $contactId, float $amountPaid, int $isCompleted, string $paymentStatus): void
    {
        if ($amountPaid <= 0) {
            $amountPaid = 0.0;
        }

        $descContactStmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM contacts WHERE contact_id = ?");
        $descContactStmt->bind_param("i", $contactId);
        $descContactStmt->execute();
        $crow = $descContactStmt->get_result()->fetch_assoc();
        $descContactStmt->close();
        $fullName = $crow['full_name'] ?? ('Contact #' . $contactId);
        $description = 'Registration Fee Paid - ' . $fullName;

        if ($isCompleted === 1) {
            // Upsert: one fee actual per contact/event
            $chk = $db->prepare("SELECT actual_id FROM prg_evnt_budget_actuals WHERE prg_evnt_id = ? AND entry_type = 'Income' AND description = ?");
            $chk->bind_param("is", $prgevntId, $description);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($existing) {
                $up = $db->prepare("UPDATE prg_evnt_budget_actuals SET amount = ? WHERE actual_id = ?");
                $up->bind_param("di", $amountPaid, $existing['actual_id']);
                $up->execute();
                $up->close();
            } else {
                $ins = $db->prepare("INSERT INTO prg_evnt_budget_actuals (prg_evnt_id, entry_type, description, amount) VALUES (?, 'Income', ?, ?)");
                $ins->bind_param("isd", $prgevntId, $description, $amountPaid);
                $ins->execute();
                $ins->close();
            }
        } else {
            // Not paid anymore -> remove the fee actual if it exists
            $del = $db->prepare("DELETE FROM prg_evnt_budget_actuals WHERE prg_evnt_id = ? AND entry_type = 'Income' AND description = ?");
            $del->bind_param("is", $prgevntId, $description);
            $del->execute();
            $del->close();
        }
    }
}
