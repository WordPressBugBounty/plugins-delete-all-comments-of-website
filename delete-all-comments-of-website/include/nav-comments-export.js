jQuery(document).ready(function($) {
    var allCsvData = [];
    var isExporting = false;
    
    $('#nav-export-comments').on('click', function(e) {
        e.preventDefault();
        
        if (isExporting) {
            return;
        }
        
        // Get form data
        var form = $(this).closest('form');
        var type = form.find('input[name="nav_delete_comment"]:checked').val();
        var dateFrom = form.find('#nav_delete_commentfrom').val();
        var dateTo = form.find('#nav_delete_commentto').val();
        
        // Reset state
        allCsvData = [];
        isExporting = true;
        
        // Show progress bar
        $('#nav-export-progress').show();
        $('.progress-bar-fill').css('width', '0%');
        $('.progress-text').text('Exporting comments... 0%');
        
        // Disable export button
        $(this).prop('disabled', true);
        
        // Start export process
        function exportBatch(offset) {
            $.ajax({
                url: navCommentsExport.ajaxurl,
                type: 'POST',
                data: {
                    action: 'nav_export_comments_ajax',
                    nonce: navCommentsExport.nonce,
                    type: type,
                    date_from: dateFrom,
                    date_to: dateTo,
                    offset: offset
                },
                success: function(response) {
                    if (response.success) {
                        // Update progress
                        var progress = response.data.progress;
                        $('.progress-bar-fill').css('width', progress + '%');
                        $('.progress-text').text('Exporting comments... ' + progress + '%');
                        
                        // Accumulate CSV data
                        allCsvData = allCsvData.concat(response.data.csv_data);
                        
                        if (response.data.is_complete) {
                            // Export complete, download file
                            var csvContent = "data:text/csv;charset=utf-8,";
                            allCsvData.forEach(function(row) {
                                var rowString = row.map(function(cell) {
                                    return '"' + (cell ? cell.toString().replace(/"/g, '""') : '') + '"';
                                }).join(',');
                                csvContent += rowString + "\r\n";
                            });
                            
                            var encodedUri = encodeURI(csvContent);
                            var link = document.createElement("a");
                            link.setAttribute("href", encodedUri);
                            link.setAttribute("download", response.data.filename);
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            
                            // Reset UI
                            $('#nav-export-progress').hide();
                            $('#nav-export-comments').prop('disabled', false);
                            isExporting = false;
                            
                            // Show success message
                            swal({
                                title: "Success!",
                                text: "Comments exported successfully.",
                                type: "success"
                            });
                        } else {
                            // Continue with next batch
                            exportBatch(response.data.processed);
                        }
                    } else {
                        // Show error message
                        swal({
                            title: "Error!",
                            text: response.data,
                            type: "error"
                        });
                        
                        // Reset UI
                        $('#nav-export-progress').hide();
                        $('#nav-export-comments').prop('disabled', false);
                        isExporting = false;
                    }
                },
                error: function() {
                    // Show error message
                    swal({
                        title: "Error!",
                        text: "An error occurred while exporting comments. Please try again.",
                        type: "error"
                    });
                    
                    // Reset UI
                    $('#nav-export-progress').hide();
                    $('#nav-export-comments').prop('disabled', false);
                    isExporting = false;
                }
            });
        }
        
        // Start with first batch
        exportBatch(0);
    });
}); 