<?php
// UI/hr/salary_management/advance_salary_view_modal.php
?>
<div class="modal fade" id="advanceSalaryDetailsModal" tabindex="-1" role="dialog" aria-labelledby="advanceSalaryDetailsModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="advanceSalaryDetailsModalLabel">Advance Salary Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Employee Information</h6>
                        <p><strong>Name:</strong> <span id="modal-employee-name"></span></p>
                        <p><strong>Employee ID:</strong> <span id="modal-employee-id"></span></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Advance Salary Details</h6>
                        <p><strong>Amount:</strong> ₹<span id="modal-advance-amount"></span></p>
                        <p><strong>Remaining Amount:</strong> ₹<span id="modal-remaining-amount"></span></p>
                        <p><strong>Status:</strong> <span id="modal-advance-status"></span></p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Additional Notes</h6>
                        <p id="modal-advance-notes"></p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Transaction Details</h6>
                        <p><strong>Created By:</strong> <span id="modal-created-by"></span></p>
                        <p><strong>Created At:</strong> <span id="modal-created-at"></span></p>
                        <p><strong>Last Updated:</strong> <span id="modal-updated-at"></span></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function loadAdvanceSalaryDetails(advanceSalaryId) {
    $.ajax({
        url: 'actions/salary_controller.php',
        method: 'GET',
        data: {
            action: 'view_advance_salary',
            advance_salary_id: advanceSalaryId
        },
        dataType: 'json',
        success: function(response) {
            if (response.error) {
                alert('Error: ' + response.error);
                return;
            }

            // Populate modal fields
            $('#modal-employee-name').text(response.employee_name);
            $('#modal-employee-id').text(response.user_id);
            $('#modal-advance-amount').text(response.amount.toFixed(2));
            $('#modal-remaining-amount').text(response.remaining_amount.toFixed(2));
            $('#modal-advance-status').text(response.status);
            $('#modal-advance-notes').text(response.notes || 'No additional notes');
            $('#modal-created-by').text(response.created_by_name);
            $('#modal-created-at').text(response.created_at);
            $('#modal-updated-at').text(response.updated_at);

            // Show the modal
            $('#advanceSalaryDetailsModal').modal('show');
        },
        error: function() {
            alert('Failed to load advance salary details');
        }
    });
}
</script>