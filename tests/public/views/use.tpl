{use class="Yiisoft\Form\Field" type="function"}
{use class="Yiisoft\Html\Html"}

{assign var="form" value=Html::form()}

{$form->open()}
{Field assign="field"}
{$form->close()}
