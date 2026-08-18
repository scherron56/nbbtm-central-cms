admin_mailer.php="sender_email">Select Sender Identity</label>
            <select name="sender_email" id="sender_email" required>
                <?php foreach ($verifiedSenders as $email => $label): ?>
                    <option value="<?= htmlspecialchars($email) ?>">
                        <?= htmlspecialchars($label) ?> &lt;<?= htmlspecialchars($email) ?>&gt;
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 2. RECIPIENT SELECTION -->
        <div class="form-group">
            <label for="target_type">Recipient Audience</label>
            <select name="target_type" id="target_type" onchange="toggleCustomRecipient(this.value)">
                <option value="custom">Single Custom Recipient</option>
                <option value="newsletter">All Newsletter Subscribers (<?= count($recipientGroups['newsletter']) ?> users)</option>
                <option value="beta_testers">Beta Testing Group (<?= count($recipientGroups['beta_testers']) ?> users)</option>
            </select>
        </div>

        <!-- Custom Recipient Inputs (Toggled via JS) -->
        <div id="custom-recipient-fields" class="row">
            <div class="form-group">
                <label for="custom_name">Recipient Name</label>
                <input type="text" name="custom_name" id="custom_name" placeholder="John Doe">
            </div>
            <div class="form-group">
                <label for="custom_email">Recipient Email</label>
                <input type="email" name="custom_email" id="custom_email" placeholder="john@example.com">
            </div>
        </div>

        <!-- 3. SUBJECT -->
        <div class="form-group">
            <label for="subject">Subject Line</label>
            <input type="text" name="subject" id="subject" required placeholder="Important update regarding your account">
        </div>

        <!-- 4. TEMPLATE BODY BUILDER -->
        <div class="form-group">
            <label for="body_template">Email Body</label>
            <textarea name="body_template" id="body_template" required placeholder="Hi {{name}},&#10;&#10;We are writing to let you know..."></textarea>
            <div class="tags">
                Supported dynamic tags: <code>{{name}}</code>, <code>{{email}}</code>, <code>{{date}}</code>
            </div>
        </div>

        <!-- 5. ATTACHMENTS -->
        <div class="form-group">
            <label for="attachments">Add Attachments (Optional, Multi-select enabled)</label>
            <input type="file" name="attachments[]" id="attachments" multiple>
        </div>

        <button type="submit">Dispatch Broadcast</button>
    </form>
</div>

<script>
function toggleCustomRecipient(value) {
    const fields = document.getElementById('custom-recipient-fields');
    fields.style.display = (value === 'custom') ? 'flex' : 'none';
}
</script>

</body>
</html>