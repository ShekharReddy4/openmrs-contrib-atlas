<?php

class AuditService
{
    private $authService;
    private $distroService;
    function __construct(DistributionService $distroService, AuthorizationService $authService) {
        $this->distroService = $distroService;
        $this->authService = $authService;
    }

    public function auditSavedSite($markerSite){
        DB::beginTransaction();
        try{
            $isSiteExisting = Audit::where('site_uuid', '=', $markerSite->id)->count() > 0;
            $action = $isSiteExisting ? "UPDATE": "ADD";
            $this->audit($markerSite, $action);
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
        }

    }

    public function auditModuleSite($markerSite, $module){
        $data = $this->getAuditData($markerSite);
        unset($data["date_created"]);
        $data["action"] = "UPDATE";
        $data["changed_by"] = 'module:' . $module;
        Audit::create($data);
    }

    public function auditDeletedSite($markerSite){
        $this->audit($markerSite, "DELETE");
    }

    private function audit($markerSite, $action){
        Log::info("Auditing " . $markerSite->id. "for ". $action);
        $data = $this->getAuditData($markerSite);
        $data["action"] = $action;
        Audit::create($data);
    }

    private function getAuditData($markerSite){

        $auditData = $markerSite->toArray();

        //$markerSite is row to update from atlas table, which contains extra column "date_changed"
        unset($auditData["date_changed"]);

        $auditData["archive_date"] = new DateTime();
        $auditData["changed_by"] = $this->authService->getPrincipal($markerSite->id);
        $auditData["site_uuid"] = $markerSite->id;
        $auditData["id"] = Uuid::uuid4()->toString();
        $auditData['distribution_name'] =  $this->distroService->getDistributionName($markerSite->distribution);

        return $auditData;
    }
}