<?php

/**
 * include/registration_fee.php
 * Shared helper: syncs a "Registration Fee Paid" income actual for a contact's
 * registration. Used by both prg_event_api.php and self_register.php so
 * self-registration behaves identically to the admin registration flow.
 */

/**
 * Sync the registration-fee actual line(s) for a contact's registration.
 *
 * Two distinct rows may exist per contact/event so the Actuals ledger reflects
 * the real cash position:
 *   - "Registration Fee Pending - <name>" : logged while money is unverified
 *   - "Registration Fee Paid - <name>"    : logged once the admin verifies payment
 *
 * When a registration is verified the pending row is removed and the paid row
 * is created/updated. When a registration is removed or reverted to pending the
 * paid row is removed and a pending row is written instead.
 */
if (!function_exists('logRegistrationFeeActual')) {
    function logRegistrationFeeActual($db, int $prgevntId, int $contactId, float $amountPaid, int $isCompleted, string $paymentStatus): void
    {
        if ($amountPaid < 0) {
            $amountPaid = 0.0;
        }

        $descContactStmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM contacts WHERE contact_id = ?");
        $descContactStmt->bind_param("i", $contactId);
        $descContactStmt->execute();
        $crow = $descContactStmt->get_result()->fetch_assoc();
        $descContactStmt->close();
        $fullName = $crow['full_name'] ?? ('Contact #' . $contactId);
        $paidDescription = 'Registration Fee Paid - ' . $fullName;
        $pendingDescription = 'Registration Fee Pending - ' . $fullName;

        // Clear any prior rows for this contact/event before writing the current state.
        $del = $db->prepare("DELETE FROM prg_evnt_budget_actuals WHERE prg_evnt_id = ? AND entry_type = 'Income' AND description IN (?, ?)");
        $del->bind_param("iss", $prgevntId, $paidDescription, $pendingDescription);
        $del->execute();
        $del->close();

        if ($amountPaid <= 0) {
            // Nothing owed / nothing paid -> no ledger line required.
            return;
        }

        // Find or auto-create the "Registration Fee" budget item for this event
        $budgetItemId = null;
        $bStmt = $db->prepare("
            SELECT budget_item_id FROM prg_evnt_budget_items 
            WHERE prg_evnt_id = ? AND LOWER(TRIM(item_description)) IN ('registration fee', 'registration fees')
            ORDER BY budget_item_id ASC LIMIT 1
        ");
        $bStmt->bind_param("i", $prgevntId);
        $bStmt->execute();
        $bRow = $bStmt->get_result()->fetch_assoc();
        $bStmt->close();

        if ($bRow) {
            $budgetItemId = (int)$bRow['budget_item_id'];
        } else {
            // Check event fee details for initial estimated amount
            $evStmt = $db->prepare("SELECT registration_fee, attend_estimate FROM programs_events WHERE prg_evnt_id = ?");
            $evStmt->bind_param("i", $prgevntId);
            $evStmt->execute();
            $evRow = $evStmt->get_result()->fetch_assoc();
            $evStmt->close();

            $fee = (float)($evRow['registration_fee'] ?? 0);
            $est = (int)($evRow['attend_estimate'] ?? 0);
            $initialAmt = ($est > 0 && $fee > 0) ? ($est * $fee) : $fee;

            $insB = $db->prepare("INSERT INTO prg_evnt_budget_items (prg_evnt_id, item_description, item_type, amount) VALUES (?, 'Registration Fee', 'Income', ?)");
            $insB->bind_param("id", $prgevntId, $initialAmt);
            $insB->execute();
            $budgetItemId = (int)$insB->insert_id;
            $insB->close();
        }

        if ($isCompleted === 1) {
            $ins = $db->prepare("INSERT INTO prg_evnt_budget_actuals (prg_evnt_id, budget_item_id, entry_type, description, amount) VALUES (?, ?, 'Income', ?, ?)");
            $ins->bind_param("iisd", $prgevntId, $budgetItemId, $paidDescription, $amountPaid);
            $ins->execute();
            $ins->close();
        } else {
            // Still pending: show a provisional line so it is visible, but it is
            // distinct from verified income (pending label in the description).
            $ins = $db->prepare("INSERT INTO prg_evnt_budget_actuals (prg_evnt_id, budget_item_id, entry_type, description, amount) VALUES (?, ?, 'Income', ?, ?)");
            $ins->bind_param("iisd", $prgevntId, $budgetItemId, $pendingDescription, $amountPaid);
            $ins->execute();
            $ins->close();
        }
    }
}
