<?php

namespace App\Services\Fel\Adapters;

use App\Services\Fel\Contracts\FelAdapterInterface;
use App\Services\Fel\FelResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Infile FEL Guatemala adapter.
 *
 * Infile API v1 flow:
 * 1. POST /api/1/create_xml  — sign + certify the DTE XML
 * 2. On success: parse UUID, serie, numero from the authorized XML response.
 *
 * Required ENV vars:
 *   FEL_ADAPTER=infile
 *   FEL_INFILE_USER=your_user@email.com
 *   FEL_INFILE_API_KEY=your_api_key
 *   FEL_INFILE_SIGNATURE_KEY=your_signature_key
 *   FEL_INFILE_API_URL=https://cert.api.infile.com.gt   (cert) or
 *                      https://api.infile.com.gt         (prod)
 */
class InfileAdapter implements FelAdapterInterface
{
    private string $apiUrl;
    private string $user;
    private string $apiKey;
    private string $signatureKey;

    public function __construct()
    {
        $this->apiUrl       = rtrim(config('restaurant.fel.infile_api_url'), '/');
        $this->user         = (string) config('restaurant.fel.infile_user');
        $this->apiKey       = (string) config('restaurant.fel.infile_api_key');
        $this->signatureKey = (string) config('restaurant.fel.infile_signature_key');
    }

    public function submit(string $xml, string $nit): FelResult
    {
        $response = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->apiUrl}/api/1/create_xml", [
                'llave_firma'  => $this->signatureKey,
                'usuario'      => $this->user,
                'llave_usuario'=> $this->apiKey,
                'nit_emisor'   => $nit,
                'xml_dte'      => base64_encode($xml),
            ]);

        if ($response->failed()) {
            return FelResult::failure("HTTP {$response->status()}: {$response->body()}");
        }

        $body = $response->json();

        // Infile returns { resultado: true/false, descripcion: "...", xml_certificado: "..." }
        if (empty($body['resultado'])) {
            $desc = $body['descripcion'] ?? 'Error desconocido de Infile';
            return FelResult::failure($desc);
        }

        $xmlAuthorized = base64_decode($body['xml_certificado'] ?? '');

        return $this->parseAuthorizedXml($xmlAuthorized);
    }

    private function parseAuthorizedXml(string $xmlAuthorized): FelResult
    {
        try {
            $doc = new \DOMDocument();
            $doc->loadXML($xmlAuthorized);

            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('dte', 'http://www.sat.gob.gt/dte/fel/0.2.0');
            $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

            // UUID is in <dte:Certificacion><dte:NumeroAutorizacion>
            $uuidNode = $xpath->query('//dte:NumeroAutorizacion')->item(0);
            $serieNode = $xpath->query('//dte:Serie')->item(0);
            $numNode   = $xpath->query('//dte:Numero')->item(0);

            $uuid  = $uuidNode?->nodeValue ?? throw new RuntimeException('UUID not found in authorized XML');
            $serie = $serieNode?->nodeValue ?? '';
            $num   = $numNode?->nodeValue ?? '';

            return FelResult::success(
                uuid: trim($uuid),
                serie: trim($serie),
                numero: trim($num),
                xmlAuthorized: $xmlAuthorized,
            );
        } catch (\Throwable $e) {
            return FelResult::failure("Error parsing Infile response: {$e->getMessage()}");
        }
    }
}
