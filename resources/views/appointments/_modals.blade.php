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
