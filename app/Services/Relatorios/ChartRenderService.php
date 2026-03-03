<?php

namespace App\Services\Relatorios;

use GuzzleHttp\Client;

class ChartRenderService
{
    /**
     * Renderiza um gráfico Chart.js como imagem PNG via API externa
     * Usando quickchart.io (serviço gratuito)
     */
    public function renderizarGrafico(array $config, int $width = 800, int $height = 400): ?string
    {
        try {
            // Construir URL do quickchart.io
            $chartConfig = [
                'type' => $config['type'],
                'data' => $config['data'],
                'options' => $config['options'] ?? [],
            ];

            $chartJson = json_encode($chartConfig);
            $encoded = urlencode($chartJson);

            $url = "https://quickchart.io/chart?chart={$encoded}&width={$width}&height={$height}&format=png";

            $client = new Client(['timeout' => 10]);
            $response = $client->get($url);

            if ($response->getStatusCode() === 200) {
                $imageData = $response->getBody()->getContents();
                return 'data:image/png;base64,' . base64_encode($imageData);
            }

            return null;

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao renderizar gráfico', [
                'erro' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Versão local usando GD (sem dependência externa)
     * Mais simples, mas menos visual
     */
    public function renderizarGraficoLocal(array $config): ?string
    {
        try {
            if ($config['type'] === 'line') {
                return $this->renderizarLinhaLocal($config);
            }

            if ($config['type'] === 'bar') {
                return $this->renderizarBarraLocal($config);
            }

            return null;

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao renderizar gráfico local', [
                'erro' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Renderiza gráfico de linha simples
     */
    private function renderizarLinhaLocal(array $config): ?string
    {
        $width = 800;
        $height = 300;

        $img = imagecreatetruecolor($width, $height);
        if (!$img) {
            return null;
        }

        // Cores
        $branco = imagecolorallocate($img, 255, 255, 255);
        $cinza = imagecolorallocate($img, 200, 200, 200);
        $azul = imagecolorallocate($img, 59, 130, 246);
        $preto = imagecolorallocate($img, 0, 0, 0);

        // Preencher fundo
        imagefilledrectangle($img, 0, 0, $width, $height, $branco);

        // Desenhar grid
        $padding = 50;
        imageline($img, $padding, $height - $padding, $width - $padding, $height - $padding, $cinza);
        imageline($img, $padding, $padding, $padding, $height - $padding, $cinza);

        // Dados
        $dataset = $config['data']['datasets'][0]['data'] ?? [];
        $labels = $config['data']['labels'] ?? [];

        if (empty($dataset)) {
            imagestring($img, 2, $width / 2 - 50, $height / 2, 'Sem dados', $preto);
            return $this->imagemParaBase64($img);
        }

        $max = max($dataset);
        if ($max === 0) {
            $max = 1;
        }

        $pointsX = count($dataset);
        $stepX = ($width - 2 * $padding) / max(1, $pointsX - 1);
        $scaleY = ($height - 2 * $padding) / $max;

        // Desenhar pontos e linhas
        $pontos = [];
        foreach ($dataset as $i => $valor) {
            $x = $padding + $i * $stepX;
            $y = $height - $padding - ($valor * $scaleY);
            $pontos[] = [$x, $y];

            // Ponto
            imagefilledellipse($img, (int)$x, (int)$y, 8, 8, $azul);
        }

        // Linhas conectando pontos
        for ($i = 0; $i < count($pontos) - 1; $i++) {
            imageline(
                $img,
                (int)$pontos[$i][0],
                (int)$pontos[$i][1],
                (int)$pontos[$i + 1][0],
                (int)$pontos[$i + 1][1],
                $azul
            );
        }

        // Labels do eixo X
        foreach ($labels as $i => $label) {
            $x = $padding + $i * $stepX;
            imagestring($img, 1, (int)$x - 15, $height - $padding + 10, substr($label, 0, 8), $preto);
        }

        // Título
        imagestring($img, 3, 20, 10, 'Quantidade Mensal', $preto);

        return $this->imagemParaBase64($img);
    }

    /**
     * Renderiza gráfico de barras simples
     */
    private function renderizarBarraLocal(array $config): ?string
    {
        $width = 600;
        $height = 300;

        $img = imagecreatetruecolor($width, $height);
        if (!$img) {
            return null;
        }

        // Cores
        $branco = imagecolorallocate($img, 255, 255, 255);
        $cinza = imagecolorallocate($img, 200, 200, 200);
        $preto = imagecolorallocate($img, 0, 0, 0);

        $cores = [
            imagecolorallocate($img, 239, 68, 68),      // Vermelho
            imagecolorallocate($img, 249, 115, 22),     // Laranja
            imagecolorallocate($img, 250, 204, 21),     // Amarelo
            imagecolorallocate($img, 132, 204, 22),     // Verde claro
            imagecolorallocate($img, 34, 197, 94),      // Verde
        ];

        // Preencher fundo
        imagefilledrectangle($img, 0, 0, $width, $height, $branco);

        // Desenhar grid
        $padding = 40;
        imageline($img, $padding, $height - $padding, $width - $padding, $height - $padding, $cinza);
        imageline($img, $padding, $padding, $padding, $height - $padding, $cinza);

        // Dados
        $dataset = $config['data']['datasets'][0]['data'] ?? [];
        $labels = $config['data']['labels'] ?? [];

        if (empty($dataset)) {
            imagestring($img, 2, $width / 2 - 50, $height / 2, 'Sem dados', $preto);
            return $this->imagemParaBase64($img);
        }

        $max = max($dataset);
        if ($max === 0) {
            $max = 1;
        }

        $barCount = count($dataset);
        $barWidth = ($width - 2 * $padding) / $barCount * 0.7;
        $spacing = ($width - 2 * $padding) / $barCount;
        $scaleY = ($height - 2 * $padding) / $max;

        // Desenhar barras
        foreach ($dataset as $i => $valor) {
            $x1 = $padding + $i * $spacing + ($spacing - $barWidth) / 2;
            $y1 = $height - $padding - ($valor * $scaleY);
            $x2 = $x1 + $barWidth;
            $y2 = $height - $padding;

            $cor = $cores[$i % count($cores)];
            imagefilledrectangle($img, (int)$x1, (int)$y1, (int)$x2, (int)$y2, $cor);
        }

        // Labels do eixo X
        foreach ($labels as $i => $label) {
            $x = $padding + $i * $spacing;
            imagestring($img, 1, (int)$x + 5, $height - $padding + 10, $label, $preto);
        }

        // Título
        imagestring($img, 3, 20, 10, 'Avaliações por Nota', $preto);

        return $this->imagemParaBase64($img);
    }

    /**
     * Converte imagem GD para base64
     */
    private function imagemParaBase64($img): ?string
    {
        ob_start();
        imagepng($img);
        $imageData = ob_get_clean();
        imagedestroy($img);

        if ($imageData === false) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($imageData);
    }
}