<?php

namespace App\Twig\Runtime;

use App\Repository\DeviceRepository;
use Doctrine\Common\Collections\Collection;
use Twig\Extension\RuntimeExtensionInterface;

class DeviceRuntime implements RuntimeExtensionInterface
{
    private array $devices;
    public function __construct(private readonly DeviceRepository $deviceRepository)
    {
        $this->devices = $this->deviceRepository->findAll();
    }

    public function getDeviceLogPath($id): ?string
    {
        foreach ($this->devices as $device) {
            if ($device->getId() === $id) {
                return  '/series/devices' . $device->getLogoPath();
            }
        }
        return null;
    }

    public function getDeviceName($id): ?string
    {
        foreach ($this->devices as $device) {
            if ($device->getId() === $id) {
                return  $device->getName();
            }
        }
        return null;
    }

    public function getDeviceSvg($id): ?string
    {
        foreach ($this->devices as $device) {
            if ($device->getId() === $id) {
                return  $device->getSvg();
            }
        }
        return null;
    }
}
