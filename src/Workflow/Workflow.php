<?php

namespace NeuronAI\Workflow;

use NeuronAI\Exceptions\WorkflowException;
use NeuronAI\Observability\Observable;
use NeuronAI\Workflow\Exporter\ExporterInterface;
use NeuronAI\Workflow\Exporter\MermaidExporter;
use NeuronAI\Workflow\Persistence\InMemoryPersistence;
use NeuronAI\Workflow\Persistence\PersistenceInterface;
use SplSubject;
use Ramsey\Uuid\Uuid;

class Workflow implements SplSubject
{
    use Observable;

    /**
     * @var NodeInterface[]
     */
    protected array $nodes = [];

    /**
     * @var Edge[]
     */
    protected array $edges = [];

    protected ?string $startNode = null;

    protected ?string $endNode = null;

    protected ExporterInterface $exporter;

    protected PersistenceInterface $persistence;

    protected string $workflowId;

    public function __construct(?PersistenceInterface $persistence = null, ?string $workflowId = null)
    {
        $this->exporter = new MermaidExporter();
        $this->persistence = $persistence ?? new InMemoryPersistence();
        $this->workflowId = $workflowId ?? Uuid::uuid4()->toString();;
    }

    public function validate(): void
    {
        if ($this->startNode === null) {
            throw new WorkflowException('Start node must be defined');
        }

        if ($this->endNode === null) {
            throw new WorkflowException('End node must be defined');
        }

        if (!isset($this->nodes[$this->startNode])) {
            throw new WorkflowException("Start node {$this->startNode} does not exist");
        }

        if (!isset($this->nodes[$this->endNode])) {
            throw new WorkflowException("End node {$this->endNode} does not exist");
        }

        foreach ($this->edges as $edge) {
            if (!isset($this->nodes[$edge->getFrom()])) {
                throw new WorkflowException("Edge from node {$edge->getFrom()} does not exist");
            }

            if (!isset($this->nodes[$edge->getTo()])) {
                throw new WorkflowException("Edge to node {$edge->getTo()} does not exist");
            }
        }
    }

    /**
     * @throws WorkflowInterrupt|WorkflowException
     */
    protected function execute(
        string $currentNode,
        WorkflowState $state,
        bool $resuming = false,
        array|string|int $humanFeedback = []
    ): WorkflowState {
        $context = new WorkflowContext(
            $this->workflowId,
            $currentNode,
            $this->persistence,
            $state
        );

        if ($resuming) {
            $context->setResuming(true, [$currentNode => $humanFeedback]);
        }

        try {
            while ($currentNode !== $this->endNode) {
                $node = $this->nodes[$currentNode];
                $node->setContext($context);

                $this->notify('workflow-node-start', $node);
                $state = $node->run($state);
                $this->notify('workflow-node-stop', $node);

                $nextNode = $this->findNextNode($currentNode, $state);

                if ($nextNode === null) {
                    throw new WorkflowException("No valid edge found from node {$currentNode}");
                }

                $currentNode = $nextNode;

                // Update the context before the next iteration or end node
                $context = new WorkflowContext(
                    $this->workflowId,
                    $currentNode,
                    $this->persistence,
                    $state
                );
            }

            $endNode = $this->nodes[$this->endNode];
            $endNode->setContext($context);
            return $endNode->run($state);

        } catch (WorkflowInterrupt $interrupt) {
            $this->persistence->save($this->workflowId, $interrupt);
            $this->notify('workflow-interrupt', $interrupt);
            throw $interrupt;
        }
    }

    /**
     * @throws WorkflowInterrupt|WorkflowException
     */
    public function run(?WorkflowState $initialState = null): WorkflowState
    {
        $this->notify('workflow-start');
        $this->validate();

        $state = $initialState ?? new WorkflowState();
        $currentNode = $this->startNode;

        $result = $this->execute($currentNode, $state);
        $this->notify('workflow-stop');

        return $result;
    }

    /**
     * @throws WorkflowInterrupt|WorkflowException
     */
    public function resume(array|string|int $humanFeedback): WorkflowState
    {
        $this->notify('workflow-resume');
        $interrupt = $this->persistence->load($this->workflowId);

        if ($interrupt === null) {
            throw new WorkflowException("No saved workflow found for ID: {$this->workflowId}");
        }

        $state = $interrupt->getState();
        $currentNode = $interrupt->getCurrentNode();

        $result = $this->execute(
            $currentNode,
            $state,
            true,
            $humanFeedback
        );
        $this->notify('workflow-stop');

        return  $result;
    }

    /**
     * @return Node[]
     */
    public function nodes(): array
    {
        return [];
    }

    /**
     * @return Edge[]
     */
    public function edges(): array
    {
        return [];
    }

    public function addNode(NodeInterface $node): self
    {
        $this->nodes[$node::class] = $node;
        return $this;
    }

    /**
     * @param NodeInterface[] $nodes
     */
    public function addNodes(array $nodes): Workflow
    {
        foreach ($nodes as $node) {
            $this->addNode($node);
        }
        return $this;
    }

    public function getNodes(): array
    {
        return \array_merge($this->nodes(), $this->nodes);
    }

    public function addEdge(Edge $edge): self
    {
        $this->edges[] = $edge;
        return $this;
    }

    /**
     * @param Edge[] $edges
     */
    public function addEdges(array $edges): Workflow
    {
        foreach ($edges as $edge) {
            $this->addEdge($edge);
        }
        return $this;
    }

    public function getEdges(): array
    {
        return \array_merge($this->edges(), $this->edges);
    }

    public function setStart(string $nodeClass): self
    {
        $this->startNode = $nodeClass;
        return $this;
    }

    public function setEnd(string $nodeClass): self
    {
        $this->endNode = $nodeClass;
        return $this;
    }

    private function findNextNode(string $currentNode, WorkflowState $state): ?string
    {
        foreach ($this->edges as $edge) {
            if ($edge->getFrom() === $currentNode && $edge->shouldExecute($state)) {
                return $edge->getTo();
            }
        }

        return null;
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    public function export(): string
    {
        return $this->exporter->export($this);
    }

    public function setExporter(ExporterInterface $exporter): Workflow
    {
        $this->exporter = $exporter;
        return $this;
    }
}
