if (data.success) {
    let html = `<div class="result-box result-success">`;
    html += `<p><i class="fas fa-check-circle"></i> ${data.message}</p>`;
    html += `<p><strong>Teachers Processed:</strong> ${data.teachers_processed}</p>`;
    html += `<p><strong>Individual Reports:</strong> ${data.individual_reports}</p>`;
    html += `<p><strong>Summary Reports:</strong> ${data.summary_reports}</p>`;
    html += `<p><strong>Total Files:</strong> ${data.total_files}</p>`;
    html += `<p><strong>Reports Location:</strong> ${data.reports_location}</p>`;
    
    // Add warning if present
    if (data.warning) {
        html += `<div class="alert alert-warning" style="margin-top: 10px;">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> ${data.warning}
                 </div>`;
    }
    
    html += `<p><a href="admin_download_reports.php" class="btn btn-success"><i class="fas fa-download"></i> View & Download Reports</a></p>`;
    html += `</div>`;
    resultDiv.innerHTML = html;
}
