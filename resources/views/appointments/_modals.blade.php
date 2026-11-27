<div class="modal fade" id="reasonModal" tabindex="-1"><div class="modal-dialog"><form method="POST" class="modal-content" id="reasonForm">@csrf
  <div class="modal-header"><h5 class="modal-title" id="reasonTitle">Reason</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><label class="form-label">Reason <span class="text-secondary fw-normal" id="reasonHint">(optional)</span></label>
    <textarea name="reason" id="reasonText" class="form-control" rows="3" maxlength="500"></textarea>
    <div class="form-text">The patient will receive this by e-mail.</div></div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Back</button><button class="btn btn-danger">Confirm</button></div>
</form></div></div>

<div class="modal fade" id="followModal" tabindex="-1"><div class="modal-dialog"><form method="POST" class="modal-content" id="followForm">@csrf
  <div class="modal-header"><h5 class="modal-title">Book follow-up visit</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body row g-3">
    <div class="col-7"><label class="form-label">Date</label><input type="date" name="date" min="{{ today()->toDateString() }}" class="form-control" required></div>
    <div class="col-5"><label class="form-label">Time</label><input type="time" name="time" class="form-control" required></div>
    <div class="col-12 form-text">Same dentist and procedure; linked to the treatment plan. The slot is checked against the schedule.</div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Back</button><button class="btn btn-primary">Book visit</button></div>
</form></div></div>

<div class="modal fade" id="quickRescheduleModal" tabindex="-1" aria-labelledby="quickRescheduleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="quickRescheduleModalLabel">Quick Reschedule (Staff)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <strong id="qrPatientName" class="fs-6"></strong><br>
          <span id="qrService" class="text-secondary small"></span> · <span id="qrDentist" class="text-secondary small"></span>
        </div>

        <div class="alert alert-info py-2 small mb-3">
          Standard booking notice and capacity limits are waived for this closure reschedule.
        </div>

        <div class="fw-semibold mb-2">Next 3 Suggested Available Slots:</div>
        <div id="qrLoading" class="text-center py-3 text-secondary">
          <div class="spinner-border spinner-border-sm text-primary me-1" role="status"></div> Loading available slots…
        </div>
        <div id="qrSlotsList" class="d-flex flex-column gap-2 mb-3"></div>

        <div class="border-top pt-3 d-flex justify-content-between align-items-center">
          <a href="#" id="qrCustomLink" class="small text-decoration-none">Pick custom date &amp; time →</a>
          <form method="POST" id="qrCancelForm" class="d-inline" onsubmit="return confirm('Cancel this appointment due to clinic closure?');">
            @csrf
            <input type="hidden" name="reason" value="Cancelled due to clinic closure">
            <button class="btn btn-sm btn-outline-danger">Cancel (Closure)</button>
          </form>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="appointmentDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Appointment Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="aptDetailsBody"></div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
