jQuery(document).ready(function($) {
    // Handle form submission
    $("#nav-disable-comments-form").on("submit", function(e) {
		
        e.preventDefault();
        var form = $(this);
        var formData = new FormData(form[0]);
        formData.append("action", "nav_save_comment_settings");
        formData.append("_wpnonce", $("#_wpnonce").val());
        
        // Show loading state
        var submitButton = form.find('button[type="submit"]');
        var originalText = submitButton.html();
        submitButton.prop('disabled', true).html('<span class="spinner is-active"></span> Saving...');
        
        $.ajax({
            url: navCommentsSettings.ajaxurl,
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: "Success!",
                        text: response.data.message || "Settings have been saved successfully.",
                        icon: "success",
                        confirmButtonColor: "#22c55e"
                    }).then(() => {
                        // Update the UI to reflect the saved state
                        var disableType = response.data.disable_type;
                        $("input[name='nav_disable_type']").prop("checked", false);
                        $("input[name='nav_disable_type'][value='" + disableType + "']").prop("checked", true);
                        
                        // Update the visual state
                        $(".nav-radio").removeClass("selected");
                        $("input[name='nav_disable_type'][value='" + disableType + "']").closest(".nav-radio").addClass("selected");
                        
                        // Update the post types container
                        $(".post-types-container").toggleClass("active", disableType === "specific");
                        $(".post-types-container input[type='checkbox']").prop("disabled", disableType === "everywhere");
                        
                        // Update the label colors
                        $(".nav-radio-label").css("color", "");
                        $("input[name='nav_disable_type'][value='" + disableType + "']").siblings(".nav-radio-label").css("color", "#2271b1");
                    });
                } else {
                    Swal.fire({
                        title: "Error!",
                        text: response.data.message || "Failed to save settings.",
                        icon: "error",
                        confirmButtonColor: "#ef4444"
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    title: "Error!",
                    text: "An error occurred while saving settings: " + error,
                    icon: "error",
                    confirmButtonColor: "#ef4444"
                });
            },
            complete: function() {
                // Reset button state
                submitButton.prop('disabled', false).html(originalText);
            }
        });
    });

    // Handle radio button changes
    $("input[name='nav_disable_type']").on("change", function() {
        var isEverywhere = $(this).val() === "everywhere";
        var isPremium = $(this).closest(".premium-feature").length > 0;
        
       
        
        $(".post-types-container").toggleClass("active", !isEverywhere);
        $(".post-types-container input[type='checkbox']").prop("disabled", isEverywhere);
        
        // Update selected state
        $(".nav-radio").removeClass("selected");
        $(this).closest(".nav-radio").addClass("selected");
        
        // Update radio label color
        $(".nav-radio-label").css("color", "");
        $(this).siblings(".nav-radio-label").css("color", "#2271b1");
    });

  



  

    // Initialize radio button states on page load
    var selectedRadio = $("input[name='nav_disable_type']:checked");
    if (selectedRadio.length) {
        var disableType = selectedRadio.val();
        selectedRadio.closest(".nav-radio").addClass("selected");
        selectedRadio.siblings(".nav-radio-label").css("color", "#2271b1");
        
        // Initialize post types container
        $(".post-types-container").toggleClass("active", disableType === "specific");
        $(".post-types-container input[type='checkbox']").prop("disabled", disableType === "everywhere");
    }

    // Handle reset button click
    $("#nav-reset-settings").on("click", function() {
        Swal.fire({
            title: "Reset Settings?",
            text: "Are you sure you want to reset all comment settings? This will enable comments everywhere and remove all restrictions.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Yes, reset settings",
            cancelButtonText: "No, cancel",
            confirmButtonColor: "#dc2626",
            cancelButtonColor: "#64748b",
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return $.ajax({
                    url: navCommentsSettings.ajaxurl,
                    type: "POST",
                    data: {
                        action: "nav_reset_comment_settings",
                        _wpnonce: $("#_wpnonce").val()
                    }
                }).then(function(response) {
                    if (response.success) {
                        return response.data;
                    }
                    throw new Error(response.data?.message || "Failed to reset settings");
                }).catch(function(error) {
                    Swal.showValidationMessage("Request failed: ${error.message || error}");
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: "Success!",
                    text: result.value?.message || "Settings have been reset successfully.",
                    icon: "success",
                    confirmButtonColor: "#22c55e"
                }).then(() => {
                    window.location.reload();
                });
            }
        });
    });

    // Handle delete confirmation
    $("#nav-delete-comments-form").on("submit", function(e) {
        e.preventDefault();
        var form = $(this);
        var selectedType = $("input[name='nav_delete_comment']:checked").val();
        var dateFrom = $("input[name='nav_delete_commentfrom']").val();
        var dateTo = $("input[name='nav_delete_commentto']").val();
        
        // Check if any option is selected
        if (!selectedType) {
            Swal.fire({
                title: "No Selection",
                text: "Please select a comment type to delete.",
                icon: "warning",
                confirmButtonColor: "#3b82f6"
            });
            return;
        }
        
        // Validate dates if they are filled
        if (dateFrom && dateTo) {
            // Set today's date to start of day
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            
            // Parse the input dates and set to start of day
            var fromDate = new Date(dateFrom);
            fromDate.setHours(0, 0, 0, 0);
            
            var toDate = new Date(dateTo);
            toDate.setHours(0, 0, 0, 0);
            
            // Compare dates
            if (fromDate > toDate) {
                Swal.fire({
                    title: "Invalid Date Range",
                    text: "Start date cannot be later than end date",
                    icon: "error",
                    confirmButtonColor: "#ef4444"
                });
                return;
            }
            
            // Allow current date but not future dates
            if (fromDate > today || toDate > today) {
                Swal.fire({
                    title: "Invalid Date Range",
                    text: "Cannot select future dates",
                    icon: "error",
                    confirmButtonColor: "#ef4444"
                });
                return;
            }
        }
        
        var commentCount = $("input[name='nav_delete_comment']:checked").closest("label").find(".comment-count").text();
        
        // Check if there are any comments to delete
        if (commentCount.includes("0 comments")) {
            Swal.fire({
                title: "No Comments",
                text: "There are no comments to delete for the selected type.",
                icon: "info",
                confirmButtonColor: "#3b82f6"
            });
            return;
        }
        
        Swal.fire({
            title: "Confirm Deletion",
            text: "Are you sure you want to delete " + commentCount + "? This action cannot be undone.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Yes, delete comments",
            cancelButtonText: "No, cancel",
            confirmButtonColor: "#ef4444",
            cancelButtonColor: "#64748b",
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return $.ajax({
                    url: form.attr("action"),
                    type: "POST",
                    data: form.serialize(),
                    processData: true,
                    contentType: "application/x-www-form-urlencoded"
                }).then(function(response) {
                    if (response.success) {
                        return response.data;
                    }
                    throw new Error(response.data?.message || "Failed to delete comments");
                }).catch(function(error) {
                    Swal.showValidationMessage("Request failed: ${error.message || error}");
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: "Success!",
                    text: result.value?.message || "Comments have been deleted successfully.",
                    icon: "success",
                    confirmButtonColor: "#22c55e"
                }).then(() => {
                    window.location.reload();
                });
            }
        });
    });

    // Handle export functionality
    $("#nav-export-comments").on("click", function() {
        var selectedType = $("input[name='nav_delete_comment']:checked").val();
        var dateFrom = $("input[name='nav_delete_commentfrom']").val();
        var dateTo = $("input[name='nav_delete_commentto']").val();
        
        // Create progress bar if it doesn't exist
        if ($("#nav-export-progress").length === 0) {
            $('<div id="nav-export-progress" style="display:none; margin-top: 20px;">' +
                '<div class="progress-bar"><div class="progress-bar-fill"></div></div>' +
                '<div class="progress-text">Exporting comments... 0%</div>' +
            '</div>').insertAfter(this);
        }
        
        $("#nav-export-progress").show();
        var progressBar = $(".progress-bar-fill");
        var progressText = $(".progress-text");
        
        function updateProgress(progress, processed, total) {
            progressBar.css("width", progress + "%");
            progressText.text("Exporting comments... " + progress + "% (" + processed + " of " + total + ")");
        }
        
        function exportComments(offset = 0) {
            $.ajax({
                url: navCommentsSettings.ajaxurl,
                type: "POST",
                data: {
                    action: "nav_export_comments_ajax",
                    _ajax_nonce: navCommentsSettings.export_nonce,
                    type: selectedType,
                    date_from: dateFrom,
                    date_to: dateTo,
                    offset: offset
                },
                success: function(response) {
                    if (response.success) {
                        updateProgress(
                            response.data.progress,
                            response.data.processed,
                            response.data.total
                        );
                        
                        if (!response.data.is_complete) {
                            exportComments(offset + 1000);
                        } else {
                            // Download the CSV file
                            var csvContent = response.data.csv_data.map(row => row.join(',')).join('\n');
                            var blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
                            var link = document.createElement("a");
                            link.href = window.URL.createObjectURL(blob);
                            link.download = response.data.filename;
                            link.click();
                            
                            $("#nav-export-progress").hide();
                            progressBar.css("width", "0%");
                            progressText.text("");
                            
                            Swal.fire({
                                title: "Export Complete!",
                                text: "Your comments have been exported successfully.",
                                icon: "success",
                                confirmButtonColor: "#22c55e"
                            });
                        }
                    } else {
                        $("#nav-export-progress").hide();
                        Swal.fire({
                            title: "Export Failed",
                            text: response.data.message || "An error occurred while exporting comments.",
                            icon: "error",
                            confirmButtonColor: "#ef4444"
                        });
                    }
                },
                error: function(xhr, status, error) {
                    $("#nav-export-progress").hide();
                    Swal.fire({
                        title: "Export Failed",
                        text: "An error occurred while exporting comments: " + error,
                        icon: "error",
                        confirmButtonColor: "#ef4444"
                    });
                }
            });
        }
        
        exportComments();
    });
}); 