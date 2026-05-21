<?php

namespace App\Services\Fel;

use App\Enums\CheckItemStatus;
use App\Models\Check;
use App\Models\FelInvoice;

/**
 * Builds the DTE XML (Documento Tributario Electrónico) for a FACT (Factura)
 * following SAT Guatemala FEL specification version 0.2.0.
 *
 * Guatemala IVA rules for FEL:
 *   - Item prices include IVA (no separate net price stored in our system)
 *   - MontoGravable = Total / 1.12   (base net)
 *   - MontoImpuesto = Total - MontoGravable
 *   - GranTotal = sum of all item totals (IVA already included)
 */
class DteXmlBuilder
{
    public function build(Check $check, FelInvoice $invoice): string
    {
        $cfg = config('restaurant.fel');
        $now = now()->format('Y-m-d\TH:i:s');

        $items = $check->items
            ->where('status.value', '!=', CheckItemStatus::Cancelled->value)
            ->values();

        $itemsXml  = $this->buildItems($items);
        $totalsXml = $this->buildTotals($check);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<dte:GTDocumento xmlns:dte="http://www.sat.gob.gt/dte/fel/0.2.0" Version="0.1">
  <dte:SAT ClaseDocumento="dte">
    <dte:DTE ID="DatosCertificacion">
      <dte:DatosEmision ID="DatosEmision">
        <dte:DatosGenerales Tipo="FACT" FechaHoraEmision="{$now}" CodigoMoneda="GTQ"/>
        <dte:Emisor
          AfiliacionIVA="GEN"
          CodigoEstablecimiento="{$cfg['establishment_code']}"
          NITEmisor="{$cfg['emisor_nit']}"
          NombreComercial="{$this->esc($cfg['emisor_commercial_name'])}"
          NombreEmisor="{$this->esc($cfg['emisor_name'])}">
          <dte:DireccionEmisor>
            <dte:Direccion>{$this->esc($cfg['emisor_address'])}</dte:Direccion>
            <dte:CodigoPostal>{$cfg['emisor_postal_code']}</dte:CodigoPostal>
            <dte:Municipio>{$this->esc($cfg['emisor_city'])}</dte:Municipio>
            <dte:Departamento>{$this->esc($cfg['emisor_department'])}</dte:Departamento>
            <dte:Pais>{$cfg['emisor_country']}</dte:Pais>
          </dte:DireccionEmisor>
        </dte:Emisor>
        <dte:Receptor
          IDReceptor="{$this->esc($invoice->receptor_nit)}"
          NombreReceptor="{$this->esc($invoice->receptor_name)}"/>
        <dte:Frases>
          <dte:Frase CodigoEscenario="1" TipoFrase="1"/>
        </dte:Frases>
        <dte:Items>
          {$itemsXml}
        </dte:Items>
        {$totalsXml}
      </dte:DatosEmision>
    </dte:DTE>
  </dte:SAT>
</dte:GTDocumento>
XML;
    }

    private function buildItems($items): string
    {
        $lines = [];
        $lineNum = 1;

        foreach ($items as $item) {
            $modTotal   = $item->modifiers->sum('price_snapshot');
            $unitPrice  = (float) $item->price_snapshot + (float) $modTotal;
            $lineTotal  = round($unitPrice * $item->quantity, 2);
            $gravable   = round($lineTotal / 1.12, 2);
            $impuesto   = round($lineTotal - $gravable, 2);

            $desc = $this->esc($item->name_snapshot);
            if ($item->modifiers->isNotEmpty()) {
                $mods = $item->modifiers->pluck('name_snapshot')->join(', ');
                $desc .= " ({$this->esc($mods)})";
            }

            $lines[] = <<<XML
          <dte:Item NumeroLinea="{$lineNum}" BienOServicio="S">
            <dte:Cantidad>{$item->quantity}</dte:Cantidad>
            <dte:UnidadMedida>UNI</dte:UnidadMedida>
            <dte:Descripcion>{$desc}</dte:Descripcion>
            <dte:PrecioUnitario>{$unitPrice}</dte:PrecioUnitario>
            <dte:Precio>{$lineTotal}</dte:Precio>
            <dte:Descuento>0.00</dte:Descuento>
            <dte:Impuestos>
              <dte:Impuesto>
                <dte:NombreCorto>IVA</dte:NombreCorto>
                <dte:CodigoUnidadGravable>1</dte:CodigoUnidadGravable>
                <dte:MontoGravable>{$gravable}</dte:MontoGravable>
                <dte:MontoImpuesto>{$impuesto}</dte:MontoImpuesto>
              </dte:Impuesto>
            </dte:Impuestos>
            <dte:Total>{$lineTotal}</dte:Total>
          </dte:Item>
XML;
            $lineNum++;
        }

        return implode("\n", $lines);
    }

    private function buildTotals(Check $check): string
    {
        $ivaTotal = (float) $check->tax;
        $grand    = (float) $check->total;

        return <<<XML
        <dte:Totales>
          <dte:TotalImpuestos>
            <dte:TotalImpuesto NombreCorto="IVA" TotalMontoImpuesto="{$ivaTotal}"/>
          </dte:TotalImpuestos>
          <dte:GranTotal>{$grand}</dte:GranTotal>
        </dte:Totales>
XML;
    }

    private function esc(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
