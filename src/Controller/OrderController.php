<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\TableOrder;
use App\Enums\OrderStatus;
use App\Form\OrderType;
use App\Repository\OrderRepository;
use App\Service\TemplateHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/order')]
final class OrderController extends AbstractController
{
    public function __construct(private readonly TemplateHelper $templateHelper)
    {
    }

    #[Route(name: 'app_order_index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository): Response
    {
        return $this->render('order/index.html.twig', [
            'orders' => $orderRepository->findBy([], ['status' => 'ASC']),
        ]);
    }

    #[Route('finish/{id}/', name: 'app_order_finish', methods: ['POST'])]
    public function finish(EntityManagerInterface $entityManager, Order $order): Response
    {
        $order->setStatus(OrderStatus::DELIVERED);
        $entityManager->persist($order);
        $entityManager->flush();

        return $this->redirectToRoute('app_order_index');
    }

    #[Route('/new/{id}', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, TableOrder $tableOrder): Response
    {
        $order = new Order();
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $stock = $order->getProduct()->getStock();

            if ($stock->getQuantity() >= $order->getQuantity()) {
                $stock->setQuantity($stock->getQuantity() - $order->getQuantity());
            }

            $value = $order->getProduct()->getPrice();
            $subtotal = $value * $order->getQuantity();

            $order->setUnitValue($value);
            $order->setSubtotal($subtotal);

            $entityManager->persist($stock);

            $order->setTableOrder($tableOrder);

            $entityManager->persist($order);
            $entityManager->flush();

            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->templateHelper->renderCrud('pedido', 'order', $order, $form);
    }

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        return $this->render('order/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_order_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Order $order, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->templateHelper->renderCrud('pedido', 'order', $order, $form, true);
    }

    #[Route('/{id}', name: 'app_order_delete', methods: ['POST'])]
    public function delete(Request $request, Order $order, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $order->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($order);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
    }
}
