<?php
$content = file_get_contents("resources/views/generate_fileno/mls_js.blade.php");
$start = strpos($content, "// STANDALONE BATCH PRINT MODAL");
if ($start !== false) {
    $newContent = substr($content, 0, $start) . "// STANDALONE BATCH PRINT MODAL
    // =====================================================================

    async function onBatchPrintDateChange() {
        const dateInput = document.getElementById(\"bpDateSelect\");
        const countInfo = document.getElementById(\"bpCountInfo\");
        const countText = document.getElementById(\"bpCountText\");
        const printBtn = document.getElementById(\"bpPrintBtn\");
        const noBatchesNotice = document.getElementById(\"bpNoBatchesNotice\");

        const selectedDate = dateInput ? dateInput.value : \"\";

        if (!selectedDate) {
            if (countInfo) countInfo.classList.add(\"hidden\");
            if (noBatchesNotice) noBatchesNotice.classList.add(\"hidden\");
            if (printBtn) printBtn.disabled = true;
            return;
        }

        if (countInfo) countInfo.classList.add(\"hidden\");
        if (noBatchesNotice) noBatchesNotice.classList.add(\"hidden\");
        if (printBtn) printBtn.disabled = true;

        try {
            const response = await fetch(\"{{ route(\"mls-fileno.batch-records\") }}\", {
                method: \"POST\",
                headers: {
                    \"Content-Type\": \"application/json\",
                    \"X-CSRF-TOKEN\": document.querySelector(\"meta[name=\"csrf-token\"]\").getAttribute(\"content\")
                },
                body: JSON.stringify({ scope: \"date\", date: selectedDate })
            });

            const payload = await response.json();

            if (!payload.success || !payload.data || payload.data.length === 0) {
                if (noBatchesNotice) noBatchesNotice.classList.remove(\"hidden\");
                return;
            }

            const count = payload.data.length;
            if (countText) countText.textContent = count;
            if (countInfo) countInfo.classList.remove(\"hidden\");
            if (printBtn) printBtn.disabled = false;

        } catch (err) {
            console.error(\"onBatchPrintDateChange error:\", err);
            if (noBatchesNotice) noBatchesNotice.classList.remove(\"hidden\");
        }
    }

    async function openBatchPrintModal() {
        const modal = document.getElementById(\"batchPrintModal\");
        if (modal) {
            modal.classList.remove(\"hidden\");
            modal.style.display = \"\";
        }
        
        const dateInput = document.getElementById(\"bpDateSelect\");
        if (dateInput && !dateInput.value) {
            const today = new Date().toISOString().split(\"T\")[0];
            dateInput.value = today;
        }
        
        await onBatchPrintDateChange();
        if (typeof lucide !== \"undefined\") lucide.createIcons();
    }

    function closeBatchPrintModal() {
        const modal = document.getElementById(\"batchPrintModal\");
        if (modal) {
            modal.classList.add(\"hidden\");
            modal.style.display = \"none\";
        }
        const countInfo = document.getElementById(\"bpCountInfo\");
        if (countInfo) countInfo.classList.add(\"hidden\");
        const printBtn = document.getElementById(\"bpPrintBtn\");
        if (printBtn) printBtn.disabled = true;
    }

    async function executeBatchPrint() {
        const dateInput = document.getElementById(\"bpDateSelect\");
        const selectedDate = dateInput ? dateInput.value : \"\";

        if (!selectedDate) {
            Swal.fire({ icon: \"warning\", title: \"No Date Selected\", text: \"Please select a date before printing.\" });
            return;
        }

        const printBtn = document.getElementById(\"bpPrintBtn\");
        if (printBtn) printBtn.disabled = true;

        try {
            const csrfToken = document.querySelector(\"meta[name=\"csrf-token\"]\").getAttribute(\"content\");
            const recordResponse = await fetch(\"/file-numbers/record-print\", {
                method: \"POST\",
                headers: { \"Content-Type\": \"application/json\", \"X-CSRF-TOKEN\": csrfToken },
                body: JSON.stringify({ reference: selectedDate, type: \"Date\", doc_type: \"Commissioning Sheet\" })
            });
            const recordData = await recordResponse.json();

            if (!recordData.success) {
                console.warn(\"Record print warning:\", recordData.message);
            }

            closeBatchPrintModal();

            await generateBatchPDF(selectedDate, \"Original\", \"date\");

        } catch (err) {
            console.error(\"executeBatchPrint error:\", err);
            Swal.fire({ icon: \"error\", title: \"Print Failed\", text: \"An unexpected error occurred. Please try again.\" });
            if (printBtn) printBtn.disabled = false;
        }
    }

    window.openBatchPrintModal = openBatchPrintModal;
    window.closeBatchPrintModal = closeBatchPrintModal;
    window.onBatchPrintDateChange = onBatchPrintDateChange;
    window.executeBatchPrint = executeBatchPrint;
</script>
";
    file_put_contents("resources/views/generate_fileno/mls_js.blade.php", $newContent);
    echo "Replaced successfully\n";
}

